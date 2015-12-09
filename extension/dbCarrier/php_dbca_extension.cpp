#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <algorithm>
#include <map>
#include <string>
#include <queue>
#include <math.h>
#include <iostream>
#include <fstream>
#include <unordered_map>
#include <boost/iostreams/device/mapped_file.hpp> 

extern "C" {
#include "tsrm_virtual_cwd.h"
}
#include "php.h"
#include "php_dbca_extension.h"
#include "php_dbca_methods.h"
#include "php_cpp_class_connectors.h"

#ifdef ZTS
ts_rsrc_id cwd_globals_id;
#else
virtual_cwd_globals cwd_globals;
#endif

#ifndef PHP_5_3
#define function_entry	zend_function_entry
#define class_entry		zend_class_entry
#endif

zend_class_entry *dbca_ce;

extern zend_object_handlers dbca_object_handlers;

static function_entry carrier_db_methods[] = {
PHP_ME(CarrierDb,  __construct,     NULL, ZEND_ACC_PUBLIC | ZEND_ACC_CTOR)
PHP_ME(CarrierDb,  getDbInfo,       NULL, ZEND_ACC_PUBLIC)
PHP_ME(CarrierDb,  get,				NULL, ZEND_ACC_PUBLIC)
{NULL, NULL, NULL}
};

zend_module_entry dbcarrier_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
STANDARD_MODULE_HEADER,
#endif
PHP_DBCARRIER_EXTNAME,
NULL,
PHP_MINIT(dbcarrier),
NULL,//PHP_MSHUTDOWN(dbcarrier),
NULL,//PHP_RINIT(),        /* Replace with NULL if there's nothing to do at request start */
NULL,//PHP_RSHUTDOWN(),    /* Replace with NULL if there's nothing to do at request end */
NULL,//PHP_MINFO(),
#if ZEND_MODULE_API_NO >= 20010901
PHP_DBCARRIER_VERSION,
#endif
STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_DBCARRIER
ZEND_GET_MODULE(dbcarrier)
#endif

PHP_MINIT_FUNCTION(dbcarrier) {
	zend_class_entry dbca;
    INIT_CLASS_ENTRY(dbca, "CarrierDb", carrier_db_methods);
    dbca_ce = zend_register_internal_class(&dbca TSRMLS_CC);
	dbca_ce->create_object = dbca_create_handler;
    memcpy(&dbca_object_handlers,
        zend_get_std_object_handlers(), sizeof(zend_object_handlers));
    dbca_object_handlers.clone_obj = NULL;
    return SUCCESS;
}
