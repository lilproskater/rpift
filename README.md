# RPiFT - Raspberry Pi File Transferer
![Raspberry Pi Logo](favicon.ico)
## Description
This web-site allows you to transfer files between your Rasbperry Pi MicroSD and any other connected device. Initially it was designed to be used on Raspberry Pi, but you can use it anywhere.

## Installation
The installation guide describes a setup on NGINX server, but you can use Apache as well.
All you need is nginx and php7.4
Installing dependencies:
```
sudo apt install nginx php7.4 php7.4-fpm php-zip
```
  
Cloning NGINX default site:
```
sudo cp /etc/nginx/sites-available/default /etc/nginx/sites-available/rpift
```
  
Editing site config file:
```
sudo nano /etc/nginx/sites-available/rpift
```
Make these changes:
```
...
server {
	listen 80;
	listen [::]:80;
	fastcgi_param  PHP_VALUE max_execution_time = 0;  # Force waiting reading the downloading file
	fastcgi_param  PHP_VALUE max_input_time = 0;  # Force waiting file uploads
	fastcgi_param  PHP_VALUE memory_limit = 4097M;  # Should be greater a bit than upload_max_filesize
	fastcgi_param  PHP_VALUE post_max_size = 4097M;  # Should be greater a bit than upload_max_filesize
	fastcgi_param  PHP_VALUE upload_max_filesize = 4096M;  # Upload up to 4 GB
...
http {
	client_max_body_size 4097M;  # Upload up to 4 GB
...
root /var/www/rpift;
# Add index.php to the list if you are using PHP
index index.php index.html index.htm index.nginx-debian.html;
server_name rpift;
...
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    # With php-fpm (or other unix sockets):
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    # With php-cgi (or other tcp sockets):
    # fastcgi_pass 127.0.0.1:9000;
}
```
Clone and move the project:
```
cd /var/www
sudo git clone https://github.com/N0n3-github/rpift.git
cd rpift
sudo mkdir html
sudo mv !(html) html
```
Remove default and enable rpift:
```
sudo rm /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/rpift /etc/nginx/sites-enabled/rpift
```
Starting php-fpm and nginx:
```
sudo systemctl restart php7.4-fpm
sudo systemctl restart nginx
```

## About config file rpift.conf
There is also a configuration file rpift.conf available. As you can see directory exposed to the file transferer can be changed there.  
Moreover permissions and blacklist can be controlled there.