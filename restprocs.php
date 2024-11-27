<?php
	/*
		Asterisk PBX simple information service
		(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!
	*/

	// Includes...
	include_once("mysql-connect.php");
	include_once("restconst.php");
	include_once("settings.php");

	// Function for preparing MySQL connections
	function prepareConnection() {
		global $conn, $conn2;
		if(!isset($conn)) {
			$conn=new mysqli(DB_SERVERNAME,DB_USERNAME,DB_PASSWORD,DB_NAME1);
			mysqli_set_charset($conn,"utf8");
		}
		if(!isset($conn2)) {
			$conn2=new mysqli(DB_SERVERNAME,DB_USERNAME,DB_PASSWORD,DB_NAME2);
			mysqli_set_charset($conn2,"utf8");
		}
		return;
	}

	// Function to get and prepare request
	function prepareRequest() {
		$uriCheckPos=strpos($_SERVER['REQUEST_URI'],"?",0);
		if($uriCheckPos === false) {
			$uriString=$_SERVER['REQUEST_URI'];
		}
		else {
			$uriString=substr($_SERVER['REQUEST_URI'],0,$uriCheckPos);
		}
		$uri=explode("/",$uriString);
		$pos=array_search(API_DIRECTORY,$uri);
		return array_filter(array_slice($uri,$pos+1));
	}

	// Helper function to return in JSON format
	function jsonReturn($inputArray) {
		echo json_encode($inputArray);
		return;
	}

	// Helper function to return errors
	function errorReturn($error) {
		jsonReturn(array("error" => $error));
		return;
	}

	// Set HTTP response code and return error JSON
	function fullErrorReturn($httpErrorCode, $error) {
		http_response_code($httpErrorCode);
		errorReturn($error);
		return;
	}

	// Set allowed methods HTTP header
	function allowedMethods($methods) {
		header("Access-Control-Allow-Methods: ".$methods);
		return;
	}

	// Set allowed methods HTTP header and proper HTTP response code
	function allowedMethodsResponse($methods) {
		http_response_code(204);
		allowedMethods($methods);
		return;
	}

	// Get REST request info and used method
	function getRequestInfo() {
		return array($_SERVER["REQUEST_METHOD"],prepareRequest());
	}

	// Helper function to try logging in
	function tryLogin($username,$password,&$userID) {
		$userID=login($username,$password);
		if($userID>0) {
			return true;
		}
		else {
			notAuthorized();
			return false;
		}
	}

	// Function to get credentials from headers
	function getCredentials(&$username, &$password) {
		if(isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
			$username=trim($_SERVER["PHP_AUTH_USER"]);
			$password=trim($_SERVER["PHP_AUTH_PW"]);
			return true;
		}
		else {
			header('WWW-Authenticate: Basic realm="'.SERVICE_NAME.'"');
			notAuthorized();
			return false;
		}
	}

	// Function to prepare date from request
	function prepareDate($input) {
		return date("Y-m-d",$input);
	}

	// Function to export date properly
	function exportDate($dateString) {
		return date("Y-m-d H:i:s", strtotime($dateString));
	}

	// Helper function to get modifiers
	function getModifiers(&$startDate, &$endDate) {
		if(array_key_exists("start",$_GET)) {
			$startDate=$_GET["start"];
		}
		else {
			$startDate=null;
		}
		if(array_key_exists("end",$_GET)) {
			$endDate=$_GET["end"];
		}
		else {
			$endDate=null;
		}
		return;
	}

	// Function to check used method and decide what to do
	function checkMethod($method) {
		if($method=="GET") {
			return true;
		}
		else if($method=="OPTIONS") {
			allowedMethodsResponse("GET");
		}
		else {
			unsupportedMethod();
		}
		return false;
	}

	// Function to check if user exists
	function userExists($username) {
		global $conn, $conn2;
		prepareConnection();
		$query="SELECT COUNT(*) FROM Noter_Users WHERE UserName=?";
		$stmt=$conn2->prepare($query);
		$stmt->bind_param("s",$username);
		$stmt->execute();
		$stmt->bind_result($resp);
		$stmt->fetch();
		return $resp;
	}

	// Function to check credentials (a.k.a. "login")
	function login($username, $password) {
		global $conn2;
		if(userExists($username)) {
			$query="SELECT ID, UserName, PasswordHash, Active FROM Noter_Users WHERE UserName=?";
			$stmt=$conn2->prepare($query);
			$stmt->bind_param("s",$username);
			$stmt->execute();
			$stmt->bind_result($id,$username,$passwordHash,$active);
			$stmt->fetch();
			if($active) {
				if(password_verify($password,$passwordHash)) {
					return $id;
				}
			}
			else {
				return -1;
			}
		}
		return 0;
	}

	// Function to check account (phone number) ownership
	function checkAccountOwnership($userID, $account) {
		global $conn2;
		prepareConnection();
		$query="SELECT Account FROM PBXNumbers p WHERE p.Account = ? AND p.OwnerID = ?;";
		$stmt=$conn2->prepare($query);
		$stmt->bind_param("ss",$account,$userID);
		$stmt->execute();
		$stmt->bind_result($resAccount);
		if($stmt->fetch()) {
			return ($resAccount == $account);
		}
		else {
			resourceNotFound();
			return false;
		}
	}

	// Function to list phone numbers owned by user
	function listAccounts($userID) {
		global $conn2;
		$query="SELECT ID, Account, Created FROM PBXNumbers p WHERE p.OwnerID = ?;";
		$stmt=$conn2->prepare($query);
		$stmt->bind_param("s",$userID);
		$stmt->execute();
		$stmt->bind_result($id,$account,$created);

		$accounts=array();

		while($stmt->fetch()) {
			array_push($accounts,array("id" => $id, "account" => $account, "account_created" => exportDate($created)));
		}

		return $accounts;
	}

	// Function to get information about phone number
	function accountInfo($userID, $phoneNumber) {
		global $conn2;
		$query="SELECT ID, Account, Created FROM PBXNumbers p WHERE p.Account = ? AND p.OwnerID = ?;";
		$stmt=$conn2->prepare($query);
		$stmt->bind_param("ss",$phoneNumber,$userID);
		$stmt->execute();
		$stmt->bind_result($id,$account,$created);
		if($stmt->fetch()){
			return array("id" => $id, "account" => $account, "account_created" => exportDate($created));
		}
		return null;
	}

	// Function to get billing
	function getBilling($phoneNumber,$startDate = null, $endDate = null) {
		global $conn;

		$query1="SELECT dst, calldate, SEC_TO_TIME(billsec) FROM cdr c WHERE c.src = ?";

		if(isset($startDate)) {
			$startDate=strtotime($startDate);
			$query2=" AND c.calldate >= DATE('".prepareDate($startDate)."')";
		}
		else {
			$query2="";
		}
		if(isset($endDate)) {
			$endDate=strtotime($endDate);
			$query3=" AND c.calldate < DATE_ADD('".prepareDate($endDate)."', INTERVAL 1 DAY)";
		}
		else {
			$query3="";
		}

		$query4=" ORDER BY ID DESC;";

		$wholeQuery=$query1.$query2.$query3.$query4;
		$stmt=$conn->prepare($wholeQuery);
		$stmt->bind_param("s",$phoneNumber);
		$stmt->execute();
		$stmt->bind_result($destination,$callDate,$callDuration);

		$billing = array();

		while($stmt->fetch()) {
			array_push($billing,array("destination" => $destination, "call_date" => exportDate($callDate), "call_duration" => $callDuration));
		}

		return $billing;
	}

	// Function to get total call count and duration
	function getTotal($phoneNumber,$startDate = null, $endDate = null) {
		global $conn;

		$query1="SELECT COUNT(*), SEC_TO_TIME(SUM(billsec)) FROM cdr c WHERE c.src = ?";

		if(isset($startDate)) {
			$startDate=strtotime($startDate);
			$query2=" AND c.calldate >= DATE('".prepareDate($startDate)."')";
		}
		else {
			$query2="";
		}
		if(isset($endDate)) {
			$endDate=strtotime($endDate);
			$query3=" AND c.calldate < DATE_ADD('".prepareDate($endDate)."', INTERVAL 1 DAY)";
		}
		else {
			$query3="";
		}

		$query4=";";

		$wholeQuery=$query1.$query2.$query3.$query4;
		$stmt=$conn->prepare($wholeQuery);
		$stmt->bind_param("s",$phoneNumber);
		$stmt->execute();
		$stmt->bind_result($callCount,$callDuration);

		if($stmt->fetch()) {
			if(!isset($callDuration)) {
				$callDuration="00:00:00";
			}
			return array("call_count" => $callCount, "call_duration" => $callDuration);
		}

		return null;
	}

	// Helper function to return internal server error
	function internalServerError() {
		fullErrorReturn(500,ERROR_INTERNAL);
		return;
	}

	// Helper function to return unsupported method error
	function unsupportedMethod() {
		fullErrorReturn(405,ERROR_INVALID_METHOD);
		return;
	}

	// Helper function to return resource not found error
	function resourceNotFound() {
		fullErrorReturn(404,ERROR_NOT_FOUND);
		return;
	}

	// Helper function to return not authorized error
	function notAuthorized() {
		fullErrorReturn(401,ERROR_NOT_AUTHORIZED);
		return;
	}

	// Helper function to create and return service information
	function pbxRESTInfo() {
		jsonReturn(array("name" => SERVICE_NAME, "version" => SERVICE_VERSION));
		return;
	}
?>
