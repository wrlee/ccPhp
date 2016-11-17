# ccPhp

Another PHP Framework. I started this micro-framework when I was frustrated by the steep learning curve needed for most PHP frameworks, at the time. The goal was to provide a thin, simple orchestration of app flow while avoiding reinvention of key components by leveraging existing outside components when they already exist (e.g., Redbean, Smarty, LessCSS, etc.). The main idea should be to get up and running as quickly as possible. You can build complexity in, as needed, but it shouldn't be needed from the get-go. 

## Getting Started

1. Copy/clone ccPhp to your web server.
2. Establish an app directory to app-specific source files. 
   1. Move or copy the **sample_project** files to that location.
   2. Adjust the reference to the ccPhp's **ccApp.php** file in the sample **app.php** file.
3. Create app's "root" file in the publicly visible web area and include the .htaccess file there (assuming you're running apache). 
   1. Move/copy the sample_project's **public** files to the pubic location. 
   2. Adjust the **index.php**'s reference to **app.php** to find it in the location on your server. 
4. Pick a primary controller as the base-class for your app's core functionality. 
   1. You might derive from **ccSimpleController**, to start. 
   2. Add methods whose names correspond to URL path names. Each method outputs content for the corresponding paths. Each method should return TRUE if it handled the URL. 
5. Add an instance of the new class to the app.php so that the class will get control when a request arrives. 
   1. Use `$app->setPage(new AppClass())` if you want to avoid using **ccChainDispatcher**
   2. Or simply add `$dispatch->addPage(new AppClass())`, which allows easy expandability (recommended).

## File organization
* "Public", web-server facing directory (e.g., under htdocs, www, public_html, or wherever directory your server exposes.
* App-specific files.
* ccPhp framework files.

The public directory contains the application's "hook" file (e.g., **index.php**) to direct the web-server to the app code. This file simply includes the app's starting file (**app.php**, in our sample). That starting file ties the app-specific code to the ccPhp framework by including **ccApp.php**.

* **index.php** -- (or whatever you want) and .htaccess (to send all processing to the PHP app-code). This file resides in the public facing area.
* **app.php** -- (or whatever you want) is the configuration file is included by index.php file and should not be in the public facing area, so put it in the app-specific area.
* **ccApp** -- The ccApp singleton instance that represents the infrastructure functionality of the app. 
* **ccRequest** -- The object representing the current request. It basically contains the state of the request. 

The framework requires a is a class to render web content. As such, the main purpose of the app is to implement the **render()** method of a class (implementing **ccPageInterface**). ccApp calls the render() method and it generates the output. It can interpret the URI (via the ccRequest object) and output content, as necessary. Simple!

There are several "controller" classes which extend this simple concept and can be used as a base-class for the app's class. A good starting class is **ccSimpleController**. With this class, you simply implement public methods whose names correspond to URL components, i.e., '/' delimited path components. Thus the URL implies an Action which corresponds to a method of the same name in the controller instance. With this class you needn't implement the render() method; its implementation correlates and calls the public method that corresponds to the URL component name. 

The an instance of the class is attached to the ccApp instance via **ccApp::setPage()**. When run, the ccApp instance calls the render() method of the class to render the page. 

### Rendering
The ccApp calls the **ccPageInterface::render()** method (and methods invoked via the **ccSimpleController::render()** as a proxy for that method). If render() return TRUE it has processed and generated output. If it returns FALSE, then it has not and ccApp generates a 404.

### ccChainDispatcher
It can be useful to break your application up into separate classes. This might be to break up handing of the URL or it may be for different kinds of handling (e.g., JSON, HTML, AJAX, ...). The **ccChainDispatcher** will call each of the classes registered with it in sequence until one of the render calls return TRUE. If none of them handle the URI (i.e., all return FALSE), the ccChainDispatcher's render() returns FALSE and 404 is triggered. So, this class enables multiple ccPageInterface implementations to be active at the same time, but ccChainDispatcher is, itself, a ccPageInterface implementation, so they all follow the same pattern. 

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

## ccApp Configuration

The idea of the framework is to get you up and running as quick as possible, then as you find a need to refine, the features are there to support that. As such, there are several defaults that you may want to override.  To start, you can perform these settings in the **app.php** file. 

* **ccApp::setWorkingDir()** By default, the framework creates a directory in the root of the application code called '.var'. This is where the application can put "disposable" files (e.g., temporary files, cache files, etc.). You can retrieve the directory with **ccApp::getWorkingDir()**. 
* **ccApp::setDevMode()** This contains flags which the framework uses to determine whether to output content. It has other flags which are dedicated to functionality as well. I'm still debating on what kinds of flags should be added.
* **local.php** vs **production.php** There are files which might be dependent on the deployment of the app. And there may be reasons that some settings (e.g., database passwords) should not be exposed in the source management system (for all to see). For these reasons, it might be useful to follow the patter of having a custom files included, if avaiable, with those deployment specific settings. Then, when sources are updated, they should not break those settings. This is approach is shown in the sample **app.php**.

# Classes
* **ccApp** There is an App singleton object that represents the application and 
its properties. (At the moment, there isn't much functionality.
* **ccRequest**  Represents the current page request. It parses the URL and 
request environment processing. In particular, it also parses the 
User-Agent string to determine characteristics about the requesting 
client. 
* **ccPageInterface** Is an interface for any kind of class that renders a page. 
The interface contains a single method, render(), that returns TRUE 
(page was rendered) or FALSE (page was not rendered). When FALSE, it is 
assumed to be a 404 response. render() takes a single parameter, 
ccRequest, which the implementation can use to determine what to render. 
A controller type of page rendering object could implement its render() 
to correlate various URL paths with specific methods of its own. Other 
implmentations might dispatch to other ccPageInterface objects, thereby 
acting as dispatch-controller objects. 

### Page Interface implementation examples:

* **ccChainDispatcher** Dispatches app flow to a "chain" of other page-interface 
objects to generate content. Each object is given a chance to process 
the request. If no objects process the request (i.e., all of their 
render() methods return FALSE), a 404 error results.  
Note: The ccRequest object is cloned for each page-object so that 
changes to the request-object won't cause side-effects with 
subsequent page-objects in the chain.
* **ccSimpleController** Uses the request object's URL path, mapping the next 
component to a method within this object, if it exists. If there is no 
URL component, then it maps to the default method name, "index", if it 
exists. If a matching method is found, before() is first called (if it 
exists) to perform common handling. If before() returns FALSE, the 
mapped method is not called. The return value of render() is the value 
of the before() (if FALSE) or mapped-method's return value, otherwise it 
returns FALSE. 
Note: This might be renamed to ccController


# TODOs

See the [todo list](https://github.com/wrlee/ccPhp/wiki/TODOs/)