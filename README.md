#ServerBuild - it Builds Servers

As the name suggests, ServerBuild is used to create servers, and configure them, it is currently focused on Vagrant
test servers, but will expand to build production servers eventually.

##Usage

php ServerBuild/command.php ServerBuild --name=DevServer --architecture=paralells --config=amazon --gitUsername=Tom

###name

Specifies the name of the server, the script will create a directory,
change to that directory, and place a Vagrantfile there.

###architecture

Specifies the type of virtualisation you want to use, currently the options are:

    - virtualbox
    - parallels
    - vmware

If this works depends on how the config file is setup,
if it only specifies one vagrant box image then you might have to use the specified architecture.

###config

This specifies the name of the config file you want to use,
you can use one of the existing ones (legacy (CentOS 6) or amazon
(loosely based on Amazon linux 2015.03, but really it's CentOS 7).

###gitUsername

If there is a repository specified in your app.yml, this will be used to clone it,
and the script will prompt for a password (so we're not keeping passwords in the config files that you may be
distributing, though Git will still save it to the git config file)