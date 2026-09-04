#include <jni.h>
#include <string>
#include <cstring>

#ifdef __cplusplus
extern "C" {
#endif

static const int XOR_KEY[] = {
    0x37, 0x42, 0x19, 0x6E, 0x2A, 0x5F, 0x3C, 0x71,
    0x48, 0x2D, 0x56, 0x1B, 0x64, 0x33, 0x7E, 0x05,
    0x4A, 0x29, 0x6F, 0x12, 0x58, 0x3D, 0x76, 0x21,
    0x63, 0x0E, 0x47, 0x3A, 0x75, 0x14, 0x5B, 0x6C
};

static const char ENCRYPTED_KEY[] = {
    'S' ^ 0x37, 'D' ^ 0x42, 'K' ^ 0x19, '-' ^ 0x6E,
    'K' ^ 0x2A, 'E' ^ 0x5F, 'Y' ^ 0x3C, '-' ^ 0x71,
    '0' ^ 0x48, '0' ^ 0x2D, '0' ^ 0x56, '0' ^ 0x1B,
    '0' ^ 0x64, '0' ^ 0x33, '0' ^ 0x7E, '0' ^ 0x05,
    '0' ^ 0x4A, '0' ^ 0x29, '0' ^ 0x6F, '0' ^ 0x12,
    '0' ^ 0x58, '0' ^ 0x3D, '0' ^ 0x76, '0' ^ 0x21,
    '0' ^ 0x63, '0' ^ 0x0E, '0' ^ 0x47, '0' ^ 0x3A,
    '0' ^ 0x75, '0' ^ 0x14, '0' ^ 0x5B, '0' ^ 0x6C,
    0
};

static char DECRYPTED_BUFFER[sizeof(ENCRYPTED_KEY)];
static volatile int DECRYPTED_READY = 0;

static void ensure_decrypted() {
    if (DECRYPTED_READY) return;
    for (int i = 0; i < (int)sizeof(ENCRYPTED_KEY) - 1; i++) {
        DECRYPTED_BUFFER[i] = ENCRYPTED_KEY[i] ^ (char)XOR_KEY[i % 16];
    }
    DECRYPTED_BUFFER[sizeof(ENCRYPTED_KEY) - 1] = 0;
    DECRYPTED_READY = 1;
}

static volatile int SCRUB_SCHEDULED = 0;

static void schedule_scrub() {
    if (SCRUB_SCHEDULED) return;
    SCRUB_SCHEDULED = 1;
}

JNIEXPORT jstring JNICALL
Java_com_sdk_SdkManager_getNativeSdkKey(JNIEnv *env, jobject thiz) {
    ensure_decrypted();
    jstring result = env->NewStringUTF(DECRYPTED_BUFFER);
    return result;
}

JNIEXPORT void JNICALL
Java_com_sdk_SdkManager_scrubNativeKey(JNIEnv *env, jobject thiz) {
    volatile char *buf = DECRYPTED_BUFFER;
    for (size_t i = 0; i < sizeof(DECRYPTED_BUFFER); i++) {
        buf[i] = 0;
    }
    DECRYPTED_READY = 0;
}

#ifdef __cplusplus
}
#endif
