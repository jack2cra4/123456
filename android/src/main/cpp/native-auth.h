#ifndef NATIVE_AUTH_H
#define NATIVE_AUTH_H

#include <jni.h>

#ifdef __cplusplus
extern "C" {
#endif

JNIEXPORT jstring JNICALL
Java_com_sdk_SdkManager_getNativeSdkKey(JNIEnv *env, jobject thiz);

JNIEXPORT void JNICALL
Java_com_sdk_SdkManager_scrubNativeKey(JNIEnv *env, jobject thiz);

#ifdef __cplusplus
}
#endif

#endif
