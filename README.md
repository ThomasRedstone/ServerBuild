#ServerBuild - it Builds Servers

As the name suggests, ServerBuild is used to create servers, and configure them, it is currently focused on Vagrant
test servers, but will expand to build production servers eventually.

##Usage

php ServerBuild/command.php ServerBuild --name=DevServer --provider=paralells --config=centos7 --gitUsername=Tom

###name

Specifies the name of the server, the script will create a directory,
change to that directory, and place a Vagrantfile there.

###provider

Specifies the type of virtualisation you want to use, currently the options are:

    - virtualbox
    - parallels
    - vmware

If this works depends on how the config file is setup,
if it only specifies one vagrant box image then you might have to use the specified provider.

###config

This specifies the name of the config file you want to use,
you can use one of the existing ones: CentOS 6, CentOS 7 or Ubuntu 1404, they live inside the distros folder, if the
distro you want isn't here, feel free to submit a pull request with a new configuration file.

###gitUsername

If there is a repository specified in your app.yml, this will be used to clone it,
and the script will prompt for a password (so we're not keeping passwords in the config files that you may be
distributing, though Git will still save it to the git config file).