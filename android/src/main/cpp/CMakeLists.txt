cmake_minimum_required(VERSION 3.18)
project(sdk_auth)

add_library(sdk_auth SHARED native-auth.cpp)

target_include_directories(sdk_auth PRIVATE ${CMAKE_CURRENT_SOURCE_DIR})

find_library(log-lib log)
target_link_libraries(sdk_auth ${log-lib})
