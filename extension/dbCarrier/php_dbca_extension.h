#ifndef PHP_DBCARRIER_H
#define PHP_DBCARRIER_H 1

#define PHP_DBCARRIER_VERSION "1.0.1"
#define PHP_DBCARRIER_EXTNAME "dbCarrier"
/*
PHP function headers here
*/

#include <list>
#include <sstream>
#include <fstream>

extern zend_module_entry db_carrier_module_entry;

#define phpext_db_carrier_ptr &db_carrier_module_entry

PHP_MINIT_FUNCTION(dbcarrier);

#endif