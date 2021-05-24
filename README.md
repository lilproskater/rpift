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

## Configuration
There is also a configuration file rpift.conf available. As you can see directory exposed to the file transferer can be changed there.  
Also you can edit OVERRIDE_FILES config value to true, if you want files with the same name to be overriden on file upload. 
```
EXPOSE_DIR=/var/www/rpift/expose_dir
OVERRIDE_FILES=false
```