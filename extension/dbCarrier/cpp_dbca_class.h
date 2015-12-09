#pragma once
#include <string>
#include <stdint.h>
#include <boost/iostreams/device/mapped_file.hpp> 
#include "php.h"

using namespace std;
class DbCarrier {
protected:
	uint32_t m_structVersion, m_buildVersion, m_buildTimestamp, m_count;
	uint32_t m_hashMin, m_hashMax, m_hashStep, m_hashListCount;
	const char *m_startPtr;
	boost::iostreams::mapped_file m_mmap;

	uint32_t hashFunc(uint32_t val);
public:
	DbCarrier(const char *fileName, const int fileNameLen TSRMLS_DC);

	uint32_t getStructVersion();
	uint32_t getBuildVersion();
	uint32_t getBuildTimestamp();
	uint32_t getCountSegments();

	string ipToCode(const char *ip TSRMLS_DC);
};