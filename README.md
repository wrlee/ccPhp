# ccPhp

Another PHP Framework. I started this micro-framework when I was frustrated by the steep learning curve needed for most PHP frameworks, at the time. The goal was to provide a thin, simple orchestration of app flow while avoiding reinvention of key components by leveraging existing outside components when they already exist (e.g., Redbean, Smarty, LessCSS, etc.). The main idea should be to get up and running as quickly as possible. You can build complexity in, as needed, but it shouldn't be needed from the get-go. 

## Getting Started

1. Copy ccPhp to your web server.
2. Establish an app directory to app-specific source files.
3. Create app's "root" file in the publicly visible web area and include the .htaccess file there (assuming you're running apache).
4. Pick a primary controller as the base-class for your app's core functionality. You might derive from **ccSimpleController**, to start. Add methods whose names correspond to URL path names. Each method outputs content for the corresponding paths. Each method should return TRUE if it handled the URL. 
5. Add an instance of the new class to the app.cpp so that the class will get control when a request arrives. 

## File organization
* "Public", web-server facing directory (e.g., under www, public_html, or wherever your server exposes directories.
* ccPhp framework files.
* App-specific files.

The public directory contains the application's "hook" file to direct the web to the app code. This is a simple 1 line inclusion of the ccPhp app's configuration file. That configuration file (actually an executable file, in itself) ties the public world to the ccPhp framework. It also ties the app-specific code to the framework. 

## Framework 
* index.php -- (or whatever you want) and .htaccess (to send all processing to the PHP app). This file resides in the public facing area.
* app.php -- (or whatever you want) is the configuration file is included by index.php file and should not be in the public facing area, so put it in the app-specific area.
* **ccApp** -- The ccApp singleton instance that represents the infrastructure functionality of the app. 
* **ccRequest** -- The object representing the current request. It basically contains the state of the request. 

In concept, there is a class needs to render web content. As such, then, the center of the app is to implement the **render()** method of a class (implementing **ccPageInterface**). All the work to interpret the URI (via the ccRequest object) and output content. Simple!

There are several "controller" classes which leverage this simple concept. A good starting class is **ccSimpleController**. With this class, you simply implement public methods whose names correspond to URL components, i.e., '/' delimited path components. Thus the URL implies an Action which corresponds to a method of the same name in the controller instance. With this class you needn't implement the render() method; it is already implemented and its purpose is to correlate and call the method that corresponds to the URL component name. 

## Output and Debugging

Development builds can be pretty noisy. But it is a pain to have to remove all the diagnostic output when moving to production; then more of a pain to add them back in, if there there a problem discovered. The framework helps to control whether and where diagnostic output should go, without making a lot of source code changes to add/delete debugging code. 

* Logging 
* Debugging statements 
* PHP errors and tracebacks

Output can be included in the output content--which is convenient during development. The output can be formatted for HTML output or output as plain text--depending on how the output will be viewed. It can also be redirected to log files with a simple setting. The default is to output as HTML within the content output, to allow getting started, easily.

* Generating Output
* Controlling Output

*Setup, usage & viewing [to be completed]*

### **ccTrace** class

    tail -f .var/logs/project.log

## Configuration

The idea of the framework is to get you up and running as quick as possible, then as you find a need to refine, the features are there to suppor that. As such, there are several defaults that you may want to override. 

* **ccApp::setWorkingDir()** By default, the framework creates a directory in the root of the application code called '.var'. This is where the application can put "disposable" files (e.g., temporary files, cache files, etc.). You can retrieve the directory with **ccApp::getWorkingDir()**. 
* **ccApp::setDevMode()** This contains flags which the framework uses to determine whether to output content. It has other flags which are dedicated to functionality as well. I'm still debating on what kinds of flags should be added.
* **local.php** vs **production.php** There are files which might be dependent on the deployment of the app. And there may be reasons that some settings (e.g., database passwords) should not be exposed in the source management system (for all to see). For these reasons, it might be useful to follow the patter of having a custom files included, if avaiable, with those deployment specific settings. Then, when sources are updated, they should not break those settings. This is approach is shown in the sample **app.php**.

# TODOs

* Clean up requirements/complexity of app.php config file, moving more defaults to ccApp.com.
* Redefine/simplify namespace usage to group all intended "public" classes to be under the same namespace. A separate namespace is for those who are augmenting the framework. And another namespace for "private" implementation.
* Define an organization for components that are added (e.g., Smarty, LessCSS, etc.)
* The namespace and "extras" may require a redefinition of how autoload() should work.
* Consider adding a unified configuration mechanism to enable simple setting (and addition) of application specific values. This may not be necessary since such settings are easily added to the **app.php** file, programmatically (and that is consistent with its approach, anyway).
