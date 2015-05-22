#ServerBuild - it Builds Servers

As the name suggests, ServerBuild is used to create servers, and configure them, it is currently focused on Vagrant
test servers, but will expand to build production servers eventually.

##Usage

php ServerBuild/command.php ServerBuild --name=DevServer --architecture=paralells --config=amazon --gitUsername=Tom

###name
Specifies the name of the server, the script will create a directory, and name the