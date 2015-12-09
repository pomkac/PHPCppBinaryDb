#pragma once

#include "php.h"
#include "cpp_dbca_class.h"


zend_object_value dbca_create_handler(zend_class_entry *type TSRMLS_DC);
void dbca_free_storage(void *object TSRMLS_DC);

struct dbca_object {
    zend_object std;
	DbCarrier *dbca;
};