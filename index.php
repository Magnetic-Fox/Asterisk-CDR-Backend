<?php
	/*
		Asterisk PBX simple information service
		(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

		Endpoints:

		GET /				- get service info
		GET /accounts			- get all phone numbers (accounts) owned by user
		GET /accounts/<NUMBER>		- get information about requested phone number
		GET /accounts/<NUMBER>/billing	- get billing for requested phone number
		GET /accounts/<NUMBER>/total	- get total call count and duration for requested phone number

		There are modifiers available for `billing` and `total` endpoints
		to set range of gathered data - `start` and `end`.

		Example use:

		GET /accounts/1001/billing?start=2023-03-13&end=2023-04-01
		GET /accounts/1001/total?start=2024-01-01&end=2023-01-31
	*/

	// Includes...
	include_once("restconst.php");
	include_once("restprocs.php");

	// Try..catch block
	try {
		// Turn off printing warnings and most errors (locally) as these may corrupt JSON output
		error_reporting(E_ERROR | E_PARSE);

		// Get all information from request
		list($method,$request)=getRequestInfo();

		// Return additional headers only if method is not OPTIONS
		if($method!="OPTIONS") {
			header("Content-Type: application/json");
			header("Access-Control-Allow-Credentials: true");
		}

		// OBJECT specified
		if(isset($request[0])) {
			// ACCOUNTS object specified
			if($request[0]=="accounts") {
				// NO PHONE NUMBER specified
				if(count($request)==1) {
					// Check if proper method was used
					if(checkMethod($method)) {
						// Try to get credentials
						if(getCredentials($username,$password)) {
							// Try to login
							if(tryLogin($username,$password,$userID)) {
								// Return list of phone numbers owned by user
								jsonReturn(listAccounts($userID));
							}
						}
					}
				}
				// PHONE NUMBER specified
				else if(count($request)==2) {
					// Get phone number
					$phoneNumber=$request[1];
					// Check if phone number is indeed a number
					if(is_numeric($phoneNumber)) {
						// Check if proper method was used
						if(checkMethod($method)) {
							// Try to get credentials
							if(getCredentials($username,$password)) {
								// Try to login
								if(tryLogin($username,$password,$userID)) {
									// Check if specified phone number is owned by requesting user
									if(checkAccountOwnership($userID,$phoneNumber)) {
										// Get phone number (account) information
										$account=accountInfo($userID,$phoneNumber);
										// Check if information returned
										if($account!=null) {
											// Return phone number information
											jsonReturn($account);
										}
										else {
											// Return error
											resourceNotFound();
										}
									}
								}
							}
						}
					}
					// Phone number is wrong
					else {
						// Return error
						resourceNotFound();
					}
				}
				// PHONE NUMBER and SUB-OBJECT specified
				else if(count($request)==3) {
					// Get phone number and modifiers
					$phoneNumber=$request[1];
					getModifiers($startDate,$endDate);
					// Check if phone number is indeed a number
					if(is_numeric($phoneNumber)) {
						// BILLING sub-object specified
						if($request[2]=="billing") {
							// Check if proper method was used
							if(checkMethod($method)) {
								// Try to get credentials
								if(getCredentials($username,$password)) {
									// Try to login
									if(tryLogin($username,$password,$userID)) {
										// Check if phone number is owned by requesting user
										if(checkAccountOwnership($userID,$phoneNumber)) {
											// Get and return billing
											jsonReturn(getBilling($phoneNumber,$startDate,$endDate));
										}
									}
								}
							}
						}
						// TOTAL sub-object specified
						else if($request[2]=="total") {
							// Check if proper method was used
							if(checkMethod($method)) {
								// Try to get credentials
								if(getCredentials($username,$password)) {
									// Try to login
									if(tryLogin($username,$password,$userID)) {
										// Check if phone number is owned by requesting user
										if(checkAccountOwnership($userID,$phoneNumber)) {
											// Get total call count and duration
											$total=getTotal($phoneNumber,$startDate,$endDate);
											// If got anything
											if(isset($total)) {
												// Return gathered information
												jsonReturn($total);
											}
											// If not
											else {
												// Return error
												resourceNotFound();
											}
										}
									}
								}
							}
						}
						// UNKNOWN sub-object specified
						else {
							// Return error
							resourceNotFound();
						}
					}
					// Phone number is wrong
					else {
						// Return error
						resourceNotFound();
					}
				}
				// UNKNOWN PARAMETERS specified
				else {
					// Return error
					resourceNotFound();
				}
			}
			// UNKNOWN object specified
			else {
				// Return error
				resourceNotFound();
			}
		}
		// NO OBJECT specified
		else {
			// Check if proper method was used
			if(checkMethod($method)) {
				// Return service information
				pbxRESTInfo();
			}
		}
	}
	// Error occurred in backend
	catch(Throwable $e) {
		// Return error
		internalServerError();
	}
?>
