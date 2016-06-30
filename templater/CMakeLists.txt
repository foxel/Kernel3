cmake_minimum_required(VERSION 3.5)

project(templater)

find_package(BISON REQUIRED)
find_package(FLEX REQUIRED)

#set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -std=c++11")

include_directories( ${CMAKE_CURRENT_BINARY_DIR} ${CMAKE_SOURCE_DIR} )

bison_target(TPL_PARSER tpl.y ${CMAKE_CURRENT_BINARY_DIR}/tpl.tab.cpp DEFINES_FILE ${CMAKE_CURRENT_BINARY_DIR}/tpl.tab.h)
flex_target(TPL_SCANNER tpl.lex ${CMAKE_CURRENT_BINARY_DIR}/tpl.yy.cpp)

add_executable(tpl2php tpl2php.cpp tpl.h ${BISON_TPL_PARSER_OUTPUTS} ${FLEX_TPL_SCANNER_OUTPUTS})
add_executable(tpl2js tpl2js.cpp tpl.h ${BISON_TPL_PARSER_OUTPUTS} ${FLEX_TPL_SCANNER_OUTPUTS})