#include <linux/module.h>
#include <linux/gpio.h>

#define YELLOW_LED 23
#define GREEN_LED  24

MODULE_LICENSE("GPL");
MODULE_AUTHOR("Neil Moir - CMP408 Adapted");
MODULE_DESCRIPTION("LED Kernel Module with Insert/Remove Logging");
MODULE_VERSION("4.0");

static int __init piirq_init(void)
{
    printk(KERN_INFO "piirq: module INSERTED\n");
    //Error handling, make sure correct GPIO pins are used
    if (!gpio_is_valid(YELLOW_LED) || !gpio_is_valid(GREEN_LED)) {
        printk(KERN_ERR "piirq: Invalid GPIO detected\n");
        return -ENODEV;
    }
    // Yellow LED
    gpio_request(YELLOW_LED, "Yellow LED");
    gpio_direction_output(YELLOW_LED, 0);
    gpio_export(YELLOW_LED, false);
    // Green LED
    gpio_request(GREEN_LED, "Green LED");
    gpio_direction_output(GREEN_LED, 0);
    gpio_export(GREEN_LED, false);

    printk(KERN_INFO "piirq: GPIO23 (Yellow) and GPIO24 (Green) ready\n");
    return 0;
}

static void __exit piirq_exit(void)
{
    gpio_set_value(YELLOW_LED, 0);
    gpio_set_value(GREEN_LED, 0);

    gpio_unexport(YELLOW_LED);
    gpio_unexport(GREEN_LED);

    gpio_free(YELLOW_LED);
    gpio_free(GREEN_LED);

    printk(KERN_INFO "piirq: module REMOVED\n");
}

module_init(piirq_init);
module_exit(piirq_exit);

