#include "php_cpp_class_connectors.h"

zend_object_handlers dbca_object_handlers;

void dbca_free_storage(void *object TSRMLS_DC) {
    dbca_object *obj = (dbca_object *)object;
    delete obj->dbca; 

    zend_hash_destroy(obj->std.properties);
    FREE_HASHTABLE(obj->std.properties);

    efree(obj);
}

zend_object_value dbca_create_handler(zend_class_entry *type TSRMLS_DC) {
    zval *tmp;
    zend_object_value retval;

    dbca_object *obj = (dbca_object *)emalloc(sizeof(dbca_object));
    memset(obj, 0, sizeof(dbca_object));
    obj->std.ce = type;

    ALLOC_HASHTABLE(obj->std.properties);
    zend_hash_init(obj->std.properties, 0, NULL, ZVAL_PTR_DTOR, 0);
    zend_hash_copy(obj->std.properties, &type->default_properties,
        (copy_ctor_func_t)zval_add_ref, (void *)&tmp, sizeof(zval *));

    retval.handle = zend_objects_store_put(obj, NULL,
        dbca_free_storage, NULL TSRMLS_CC);
    retval.handlers = &dbca_object_handlers;

    return retval;
}