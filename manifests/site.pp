# Install our dependencies

exec { "apt-get update":
  path => "/usr/bin",
}

package { "python-software-properties":
  ensure => present,
  before => Exec["add-apt-repository ppa:ondrej/php5"],
  require => Exec["apt-get update"],
}

exec { "add-apt-repository ppa:ondrej/php5":
  command => "/usr/bin/add-apt-repository ppa:ondrej/php5",
  require => Package["python-software-properties"]
}

exec { "apt-get update 2":
  command => "/usr/bin/apt-get update",
  require => Exec["add-apt-repository ppa:ondrej/php5"],
}

package {"apache2":
  ensure => present,
  require => Exec["apt-get update 2"],
}

service { "apache2":
  ensure => "running",
  require => Package["apache2"]
}

package {["mysql-server", "mysql-client"]:
  ensure => installed,
  require => Exec["apt-get update 2"]
}

service { "mysql":
  ensure  => running,
  require => Package["mysql-server"],
}

package { ["php5-common", "libapache2-mod-php5", "php5-cli", "php-apc", "php5-mysql", "php5-gd", "php5-mysqlnd"]:
  ensure => installed,
  notify => Service["apache2"],
  require => [Exec["apt-get update 2"], Package["mysql-client"], Package["apache2"]],
}

exec { "/usr/sbin/a2enmod rewrite" :
  unless => "/bin/readlink -e /etc/apache2/mods-enabled/rewrite.load",
  notify => Service[apache2],
  require => Package["apache2"]
}

# Set up a new VirtualHost

file {"/var/www":
  ensure => "link",
  target => "/vagrant",
  require => Package["apache2"],
  notify => Service["apache2"],
  replace => yes,
  force => true,
}

file { "/etc/apache2/sites-available/default":
  ensure => "link",
  target => "/vagrant/manifests/assets/vhost.conf",
  require => Package["apache2"],
  notify => Service["apache2"],
  replace => yes,
  force => true,
}

# Set Apache to run as the Vagrant user

exec { "ApacheUserChange" :
  command => "/bin/sed -i 's/APACHE_RUN_USER=www-data/APACHE_RUN_USER=vagrant/' /etc/apache2/envvars",
  onlyif  => "/bin/grep -c 'APACHE_RUN_USER=www-data' /etc/apache2/envvars",
  require => Package["apache2"],
  notify  => Service["apache2"],
}

exec { "ApacheGroupChange" :
  command => "/bin/sed -i 's/APACHE_RUN_GROUP=www-data/APACHE_RUN_GROUP=vagrant/' /etc/apache2/envvars",
  onlyif  => "/bin/grep -c 'APACHE_RUN_GROUP=www-data' /etc/apache2/envvars",
  require => Package["apache2"],
  notify  => Service["apache2"],
}

exec { "apache_lockfile_permissions" :
  command => "/bin/chown -R vagrant:www-data /var/lock/apache2",
  require => Package["apache2"],
  notify  => Service["apache2"],
}

# Setup the intial database

exec { "ubirimi database" :
  command => "/usr/bin/mysql -uroot -e \"create database ubirimi;\"",
  require => Service["mysql"],
}