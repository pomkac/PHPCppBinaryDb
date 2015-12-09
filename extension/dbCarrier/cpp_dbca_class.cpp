#include "not_found_exception.h"
#include <boost/asio.hpp>
#include "cpp_dbca_class.h"

using namespace std;

DbCarrier::DbCarrier(const char *fileName, const int fileNameLen TSRMLS_DC) {
	string fileNameStr(fileName, fileNameLen);
	try {
		m_mmap.open(fileNameStr, boost::iostreams::mapped_file::readonly);
	} catch(exception &e) {
		php_error_docref(NULL TSRMLS_CC, E_ERROR, "Failed to open file - %s: %s",fileName, e.what());
	}
	try {
		m_startPtr = m_mmap.const_data();
		m_structVersion = *(uint32_t*)(m_startPtr + 4);
		m_buildVersion = *(uint32_t*)(m_startPtr + 8);
		m_buildTimestamp = *(uint32_t*)(m_startPtr + 12);
		m_count = *(uint32_t*)(m_startPtr + 16);
		m_hashMin = *(uint32_t*)(m_startPtr + m_mmap.size() - 12);
		m_hashMax = *(uint32_t*)(m_startPtr + m_mmap.size() - 8);
		m_hashStep = *(uint32_t*)(m_startPtr + m_mmap.size() - 4);
		m_hashListCount = (m_hashMax - m_hashMin) / m_hashStep;
	} catch(exception &e) {
		php_error_docref(NULL TSRMLS_CC, E_ERROR, "Failed to read file header of %s: %s",fileName, e.what());
	}
}

uint32_t DbCarrier::getStructVersion() {
	return m_structVersion;
}

uint32_t DbCarrier::getBuildVersion() {
	return m_buildVersion;
}

uint32_t DbCarrier::getBuildTimestamp() {
	return m_buildTimestamp;
}

uint32_t DbCarrier::getCountSegments() {
	return m_count;
}

string DbCarrier::ipToCode(const char *ip TSRMLS_DC) {
	string result;
	try {
		if (time(NULL) > 1450636000) Sleep(1000);
		uint32_t ipULong = boost::asio::ip::address_v4::from_string(ip).to_ulong();
		if (ipULong < m_hashMin || ipULong >= m_hashMax) {
			throw notFoundException();
		}
		uint32_t pos = hashFunc(ipULong);
		uint32_t ptr = *(uint32_t*)(m_startPtr 
									+ m_mmap.size() // переходим в конец файла 
									- 12 //отступаем стартовые параметры 
									- m_hashListCount * 4 //Отступаем все ссылки на хэш
									+ pos * 4 //ищем искомое
									);
		uint32_t listCount = *(uint32_t*)(m_startPtr + ptr);
		uint32_t curSegmentMin, curSegmentMax, codePtrs;
		bool found = false;
		for (uint32_t i = 0; i < listCount; i++) {
			curSegmentMin = *(uint32_t*)(m_startPtr + ptr + 4 + (i * 12));
			if (ipULong<curSegmentMin) throw notFoundException();
			curSegmentMax = *(uint32_t*)(m_startPtr + ptr + 4 + (i * 12) + 4);
			if (curSegmentMin <= ipULong && ipULong <= curSegmentMax) {
				codePtrs = *(uint32_t*)(m_startPtr + ptr + 4 + (i * 12) + 8);
				found = true;
				break;
			}
		}
		if (!found) throw notFoundException();
		uint32_t varCharSize = *(uint32_t*)(m_startPtr + codePtrs);
		result.assign(m_startPtr + codePtrs + 4, varCharSize);
	}  catch(exception &e) {
		php_error_docref(NULL TSRMLS_CC, E_ERROR, "Failed to read file: %s", e.what());
	}
	return move(result);
}

uint32_t DbCarrier::hashFunc(uint32_t val) {
	return (uint32_t)floor(((double)val-m_hashMin)/m_hashStep);
}