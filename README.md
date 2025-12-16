<img width="600" height="600" alt="image" src="https://github.com/user-attachments/assets/f1a246c5-abf5-49c0-87a2-dfce51c53381" />



# Spy-Pi: HOW TO

## Materials

### Required
- Raspberry Pi Zero W + microSD + power
- Breadboard + jumper wires
- 2x LEDs (Green and Yellow)
- 2x resistors (typically **220Ω–330Ω**)
- I2S MEMS microphone

## Step 1: Wire up your components

| Item | BCM GPIO | Physical Pin | Connection
|------|----------|--------------|-----------|
| Green LED | GPIO23 | Pin 16 | Output “status/OK” indicator |
| Yellow LED | GPIO24 | Pin 18 | Output “recording/in-progress” indicator |
| Ground | GND | Pin 14 (or any GND) | LED cathodes to GND |

| Mic Pin | BCM GPIO | Physical Pin |
|--------|----------------|-----|
| 3V3 | 3.3V | Pin 1 |
| GND | GND | Pin 6 |
| BCLK | GPIO18 | Pin 12 |
| LRCL | GPIO19 | Pin 35 |
| DOUT | GPIO20 | Pin 38 |

**LED polarity reminder**
- **Anode** = long leg (goes toward GPIO through resistor)
- **Cathode** = short leg (goes to GND)

## Step 2: Edit config.txt of Raspberry Pi to detect microphone

```bash
sudo nano /boot/config.txt
```

  Add: 

  - dtparam=i2s=on
  - dtoverlay=googlevoicehat-soundcard

Save and reboot
```bash
sudo reboot
```

#### Test LEDs
```bash
echo 23 | sudo tee /sys/class/gpio/export
echo out | sudo tee /sys/class/gpio/gpio23/direction
echo 1   | sudo tee /sys/class/gpio/gpio23/value   # ON
echo 0   | sudo tee /sys/class/gpio/gpio23/value   # OFF
echo 23  | sudo tee /sys/class/gpio/unexport
```

Repeat for GPIO24, just replace 23 with 24.

#### Verify soundcard is present and test recording

```bash
arecord -l
//
cat /proc/asound/cards
//
arecord -D plughw:0,0 -c1 -r 48000 -f S32_LE test.wav -d 5
```

***To test, assuming you're SSH'd into the Pi like I was***
```powershell
scp pi@<PI_IP>:/home/pi/test.wav .
```

## Step 3: Kernel Module Build/Insert

This project was all completed under the same folder directory:
/home/pi/Project/, therefore assuming all files are under this root dir we can proceed.

First, verify the kernel using:

```bash
uname -r
```

Then, install headers

```bash
sudo apt update
sudo apt install -y raspberry-pi-kernel-headers
```

Verify the build exists

```bash
ls /lib/module/$(uname -r)/build
```

Assuming the Makefile is in the same dir as the piirq.c file, build it using (however, all necessary files have been included):

```bash
make clean
make
```

You should now have a piirq.ko file, you can insmod it to see it in action, but lets get it autoloading at boot:

```bash
echo "piirq" | sudo tee /etc/modules-load.d/piirq.conf
```

Copy the module into /lib/modules and then register it using depmod to rebuild the dependencies

```bash
sudo cp /home/pi/Project/piirq.ko /lib/modules/$(uname -r)/extra/
sudo depmod -a
```

Reboot your machine, and then dmesg or lsmod, grep for piirq and it should be there.

## Step 4: AWS Setup
In AWS Console:
- Set up an S3 bucket, name it whatever you want, and make sure you take note of the region and name of the bucket (you'll need to change bucket name in python script).

- Create an IAM user, I used: spy-pi-device
  - Give the user permissions to access S3 bucket, I gave fullaccess for testing, but for security give at least minimum s3:PutObject
 
- Create access keys (programmatic access)
-   Take note of the Access Key ID and Secret Access Key (obviously don't share the secret one)

Next, we need to install AWS CLI on the Pi, once installed, run:

```bash
aws configure
```

It will prompt you to enter in the Access Key ID, Secret Key, Default region name. Don't worry about default output format, but you can set it to json to be safe (it defaults to that).

Test it:

```bash
aws s3 ls
aws s3 ls s3://{name of your bucket}
```

For Elastic Beanstalk, you will then need to go back to console, and create an environment, name it what you want, but it needs to be running a PHP environment. Take all the PHP files and zip them into a single file, ensure they are underneath the root of the zip file, so no folder above them. If they exist in a folder within the zip, it wont work, EB will complain or not compile properly. This has been included in the PHP folder as a zip.

- Within your EB environment, make sure you choose the optional logging area, and set some environment variables, they are named in the PHP config.php file what they should be named, give them any value you want as long as they have the variable names in the config.php file. From there, once EB is deployed, you should be good to authenticate!


## Step 5: Python Script

The python script should be good to go, however there are some changes that will need to be made in order to align it with your configuration, assuming you've set up the items in Step 4 (I chose to do AWS first here so we can work logically), although you can test some of the items by tweaking the Python script (comment out some functions and just run record_audio() etc.) to not need AWS access if you want to just save a wav locally that's timestamped, and to test the LEDs.

To communicate with AWS, you need to make sure you've configured AWS CLI with your access keys, renamed your S3 bucket name and prefix (prefix can be empty though).

## Enjoy your local, secure, lightweight audio device with AWS connectivity.

<img width="671" height="510" alt="image" src="https://github.com/user-attachments/assets/01480bea-85cd-4c6a-8395-e96c79885a56" />

<img width="1021" height="1004" alt="image" src="https://github.com/user-attachments/assets/5a0f8197-600d-40b3-85b4-8b8a7131b684" />
