# Asterisk CDR Backend

This repository contains very simple backend for retrieving billing information from Asterisk CDR database.
It's build to support user-owned phone numbers, which information is stored in additional database.
User credentials are retrieved from Noter tables (refer to: [Noter Backend](https://github.com/Magnetic-Fox/Noter-Backend))

## What is needed to run this codes?

* Working Apache, PHP and MySQL/MariaDB servers/components
* Asterisk configured to utilize ODBC (to store CDR information in standard database)
* Proper database configuration (tables etc.)

## Table info

In addition to standard `Noter_Users` table used to logging user in and typical `asterisk-cdr` table, one additional table has to be created.

`PBXNumbers` organized like this:
```
+---------+------------------+----------+----------------+-------------+----------------------+
| name    | type             | null?    | extra          | key type    | default              |
+---------+------------------+----------+----------------+-------------+----------------------+
| ID      | int(10) unsigned | NOT NULL | AUTO INCREMENT | PRIMARY KEY |                      |
+---------+------------------+----------+----------------+-------------+----------------------+
| Account | varchar(100)     | NOT NULL |                |             |                      |
+---------+------------------+----------+----------------+-------------+----------------------+
| Created | datetime(6)      | NOT NULL |                |             | current_timestamp(6) |
+---------+------------------+----------+----------------+-------------+----------------------+
| OwnerID | int(10) unsigned | NOT NULL |                | FOREIGN KEY |                      |
+---------+------------------+----------+----------------+-------------+----------------------+
```

`OwnerID` has to be foreign key referring to ID of the user in `Noter_Users` table.
Typical Asterisk table probably contains columns with names `ID`, `accountcode`, `calldate`, `src`, `dst`, `dcontext`, `clid`, `channel`, `dstchannel`, `lastapp`, `lastdata`, `duration`, `billsec`, `disposition`, `amaflags`, `uniqueid` and `userfield`. Or at least I use those columns. ;) I think it's best to look at the Asterisk documentation for proper table construction scheme.

## Disclaimer

I've made much effort to provide here working and checked codes with hope they will be useful.
**However, these codes are provided here "AS IS", with absolutely no warranty! I take no responsibility for using them - DO IT ON YOUR OWN RISK!**

## License

Codes provided here are free for personal use.
If you like to use any part of these codes in your software, just please give me some simple credits and it will be okay. ;)
In case you would like to make paid software and use parts of these codes - please, contact me before.

*Bartłomiej "Magnetic-Fox" Węgrzyn,*
*November 27, 2024*
