# RPiFT - Raspberry Pi File Transferer
![Raspberry Pi Logo](favicon.ico)
## Description
This web-site allows you to transfer files between your Rasbperry Pi MicroSD and any other connected device. Initially it was designed to be used on Raspberry Pi, but you can use it anywhere.

## Installation
Installing dependencies:
```
sudo apt install php7.4 php7.4-fpm php-zip
```
Starting php-fpm:
```
sudo systemctl restart php7.4-fpm
```
Clone and move the project:
```
cd /var/www
sudo git clone https://github.com/N0n3-github/rpift.git
sudo chown -R www-data:www-data ./rpift
```

## NGINX Installation & Configuration
Installing NGINX & cloning config:
```
sudo apt install nginx
cd rpift
sudo cp ./nginx_rpift.conf /etc/nginx/sites-available/rpift
```
Remove default and enable rpift:
```
sudo rm /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/rpift /etc/nginx/sites-enabled/rpift
```
Starting NGINX:
```
sudo systemctl restart nginx
```

## Apache Installation & Configuration
Installing Apache & enabling site:
```
sudo apt install apache2
cd rpift
sudo a2ensite rpift.conf
```
Changing php.ini values:
```
sudo nano /etc/php/7.4/apache2/php.ini
max_execution_time = 0
max_input_time = 0
memory_limit = 4097M
post_max_size = 4097M
upload_max_filesize = 4096M
```
Starting Apache:
```
sudo systemctl restart apache2
```
Apache .htaccess configuration comes out of the box

## About config file rpift.conf
There is also a configuration file rpift.conf available. As you can see directory exposed to the file transferer can be changed there.  
Moreover permissions and blacklist can be controlled there.
