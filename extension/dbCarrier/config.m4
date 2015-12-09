PHP_ARG_WITH(dbca,     whether to enable DbCarrier support,
[  --with-dbca  Enable DbCarrier support. ], yes)

PHP_ARG_WITH(boost-dir,    optional boost install prefix,
[  --with-boost-dir[=DIR]  Optional path to boost installation.], no, no)

if test "x${PHP_DBCA}" != "xno"; then

	CXX_FLAGS="-std=c++0x"
	PHP_REQUIRE_CXX()

	# Add boost includes
	AC_MSG_CHECKING([boost installation])
	if test "x${PHP_BOOST_DIR}" != "xyes" -a "x${PHP_BOOST_DIR}" != "xno"; then
		if test ! -r ${PHP_BOOST_DIR}/include/boost/shared_ptr.hpp; then
			AC_MSG_ERROR([${PHP_BOOST_DIR}/include/boost/shared_ptr.hpp not found])
		fi
	else
		for dir in /usr /usr/local /opt/local; do
			test -r "${dir}/include/boost/shared_ptr.hpp" && PHP_BOOST_DIR="${dir}" && break
		done
		if test "x${PHP_BOOST_DIR}" = "x"; then
			AC_MSG_ERROR([boost installation not found])
		fi
	fi
	AC_MSG_RESULT([found in ${PHP_BOOST_DIR}])
	PHP_ADD_INCLUDE(${PHP_BOOST_DIR}/include)

	PHP_ADD_LIBRARY(stdc++, DBCA_SHARED_LIBADD)

	PHP_SUBST(DBCA_SHARED_LIBADD)
	PHP_NEW_EXTENSION(dbca, cpp_dbca_class.cpp php_cpp_class_connectors.cpp php_dbca_extension.cpp php_dbca_methods.cpp, $ext_shared,,-Wall)
fi