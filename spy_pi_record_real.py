#!/usr/bin/env python3
"""
Author: Neil McGregor Moir
Degree: BSc Ethical Hacking
Year: 4

Spy-Pi Microphone script

- Assumes custom LKM 'piirq' is loaded at boot
  and has already configured GPIO23 and GPIO24 via sysfs.

- Yellow LED (GPIO24): ON while recording.

- Green LED (GPIO23): Blinks on successful S3 upload.

Security notes:

- User-space only confirms LKM is loaded, doesn't insert or remove anything.

- Audio files are uploaded to S3 via AWS CLI over HTTPS (TLS).
"""

import os
import time
import subprocess
from datetime import datetime

# KERNEL MODULE SETTINGS
MODULE_NAME = "piirq"

# GPIO Settings
YELLOW_GPIO = 24   # recording indicator
GREEN_GPIO  = 23   # upload success indicator

# AUDIO SETTINGS
DEVICE   = "plughw:0,0"
RATE     = 48000
FORMAT   = "S32_LE"
CHANNELS = 1
SECONDS  = 5   # adjust for longer/shorter recordings

# S3 SETTINGS
S3_BUCKET = "spy-pi-audio-c0ggerz"
S3_PREFIX = "pi-recordings/"   # folder to save the recordings into, can be empty but keeps things clean


#  Kernel module check (no loading here, JUST validation)
def ensure_module_loaded():
    """Check that the kernel module is loaded; do NOT load it."""
    if os.path.exists(f"/sys/module/{MODULE_NAME}"):
        print(f"[+] Kernel module '{MODULE_NAME}' is loaded.")
    else:
        print(f"[!] Kernel module '{MODULE_NAME}' is NOT loaded.")
        print("    Make sure it's installed and configured to load at boot, e.g.:")
        print("      - /lib/modules/$(uname -r)/extra/piirq.ko")
        print("      - /etc/modules-load.d/piirq.conf contains 'piirq'")
        raise SystemExit(1)


#  GPIO helpers (via sysfs)
def gpio_value_path(num: int) -> str:
    return f"/sys/class/gpio/gpio{num}/value"


def write_gpio(num: int, value: int):
    """
    Write 0 or 1 to a GPIO value file.
    Assumes the LKM exported and set direction=out.
    """
    path = gpio_value_path(num)

    for r in range(10):
        if os.path.exists(path):
            break
        time.sleep(0.1)

    if not os.path.exists(path):
        print(f"[!] GPIO path not found: {path}")
        return

    try:
        with open(path, "w") as f:
            f.write("1" if value else "0")
    except Exception as e:
        print(f"[!] Failed to write GPIO {num}: {e}")


def yellow_on():
    write_gpio(YELLOW_GPIO, 1)


def yellow_off():
    write_gpio(YELLOW_GPIO, 0)


def green_on():
    write_gpio(GREEN_GPIO, 1)


def green_off():
    write_gpio(GREEN_GPIO, 0)


def blink_green(times: int = 4, delay: float = 0.2):
    for t in range(times):
        green_on()
        time.sleep(delay)
        green_off()
        time.sleep(delay)


def blink_both_error(times: int = 3, delay: float = 0.15):
    # Flashing blinks to indicate a serious error (like upload fail).
    for t in range(times):
        yellow_on()
        green_on()
        time.sleep(delay)
        yellow_off()
        green_off()
        time.sleep(delay)


#  Audio recording
def record_audio() -> str:
    input("\nPress ENTER to start recording...")

    timestamp = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
    filename = f"recording_{timestamp}.wav"

    command = [
        "arecord",
        "-D", DEVICE,
        "-c", str(CHANNELS),
        "-r", str(RATE),
        "-f", FORMAT,
        filename,
        "-d", str(SECONDS),
    ]

    print("\n[+] Recording in progress...")
    print("[+] Command:", " ".join(command))

    yellow_on()
    try:
        subprocess.check_call(command)
        print(f"[+] Recording complete: {filename}")
    except subprocess.CalledProcessError as e:
        print("[!] Recording failed:", e)
        yellow_off()
        blink_both_error()
        raise
    finally:
        yellow_off()

    return filename


#  S3 upload via AWS CLI
def upload_to_s3(filename: str):
    """
    Upload the given file to S3 using the AWS CLI.
    - aws CLI must be installed and on PATH
    
    - aws configure has already been run for user
    
    - IAM user has permissions to PutObject to the target bucket

    - Uses SSE-S3 (AES256) for server-side encryption.
    """
    key = S3_PREFIX + os.path.basename(filename)

    command = [
        "aws", "s3", "cp",
        filename,
        f"s3://{S3_BUCKET}/{key}",
        "--sse", "AES256",   # enable server-side encryption
    ]

    print(f"\n[+] Uploading '{filename}' to s3://{S3_BUCKET}/{key} ...")
    print("[+] Command:", " ".join(command))

    try:
        subprocess.check_call(command)
        print("[+] Upload successful.")
        print("[+] Blinking green LED to indicate success...")
        blink_green()
    except subprocess.CalledProcessError as e:
        print("[!] Upload failed:", e)
        blink_both_error()
        raise

def main():
    print("---> Spy-Pi Audio Recorder <---")
    ensure_module_loaded()

    try:
        wavfile = record_audio()
        upload_to_s3(wavfile)
    except KeyboardInterrupt:
        print("\n[!] Interrupted by user.")
    finally:
        # Make sure LEDs are off when exited
        yellow_off()
        green_off()

    print("\n[+] Done.\n")


if __name__ == "__main__":
    main()

