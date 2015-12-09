#include "cpp_dbca_class.h"
#include "php_cpp_class_connectors.h"
#include "not_found_exception.h"
#include "php_dbca_methods.h"

using namespace std;
PHP_METHOD(CarrierDb, __construct){
	char *fileName;
    int fileNameLen;
    DbCarrier *dbca = NULL;
    zval *object = getThis();
 
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &fileName, &fileNameLen) == FAILURE) {
        RETURN_NULL();
    }
	dbca = new DbCarrier(fileName, fileNameLen TSRMLS_CC);
    dbca_object *obj = (dbca_object *)zend_object_store_get_object(object TSRMLS_CC);
    obj->dbca = dbca;
}

PHP_METHOD(CarrierDb, getDbInfo){
	DbCarrier *dbca;
    dbca_object *obj = (dbca_object *)zend_object_store_get_object(getThis() TSRMLS_CC);
    dbca = obj->dbca;
    if (dbca != NULL) {
		array_init(return_value);
		add_assoc_long(return_value, "structVersion", dbca->getStructVersion());
        add_assoc_long(return_value, "buildVersion", dbca->getBuildVersion());
		add_assoc_long(return_value, "buildTimestamp", dbca->getBuildTimestamp());
		add_assoc_long(return_value, "recCount", dbca->getCountSegments());
		return;
    } else {
		RETURN_NULL();
	}
}

PHP_METHOD(CarrierDb, get){
	DbCarrier *dbca;
    char *ip;
    int ipLen;
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ip, &ipLen) == FAILURE) {
        RETURN_NULL();
    }
	
	dbca_object *obj = (dbca_object *)zend_object_store_get_object(getThis() TSRMLS_CC);
    dbca = obj->dbca;
    if (dbca != NULL) {
		try {
			std::string result = dbca->ipToCode(ip TSRMLS_CC);
			RETURN_STRINGL((char*)result.c_str(), result.size(), 1);
		} catch(notFoundException&)	{
			RETURN_NULL();
		} catch(exception& e)	{
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to get ip carrier code: %s", e.what());
			RETURN_NULL();
		}
    } else {
		RETURN_NULL();
	}
}