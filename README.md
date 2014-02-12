yiiCookieControl
================

CookieControl is an application component for the Yii framework to deal with the
requirements of some EU countries to get explicit user consent for using cookies 
to store data. It wraps around the script provided by Civic.

Requirements 
================
Yii 1.1 or above (tested on 1.1.14)
An API key from Civic
[optional] PHP GeoIP extension; if you explicitly wish to limit the question to some countries. This functionality is also provided by the commercial version of the script by civic. We can do the same with PHP.

Installation 
================

Move cookieControl folder in your applications extensions folder (default: protected/extensions).
Using extension 

Configure the application component in your config (/protected/config/main.php)

[code]
'cookieControl' => array(
    'class' =>'ext.cookieControl.cookieControl',
    'apikey' => '<insert your API key>',
    'options' => array(
        'position'=>'CookieControl.POS_RIGHT',
    )
),
[/code]

API key get one for your site from the configurator. options some cookieControl options can be overrided.

In your controllers or in the overloading controller you should include the following code

[code]
protected function beforeRender($view)
{
    $return = parent::beforeRender($view);
    if (Yii::app()->cookieControl->renderOnConsent())
    {
        // add code here that'll place cookies, ie:
        Yii::app()->googleAnalytics->render();
        Yii::app()->piwik->render();
        Yii::app()->clientScript->registerScriptFile('<cookie dependent script>');
        Yii::app()->clientScript->registerScript('somescript', '<other cookie dependent code>');
    }
    return $return;
}
[/code]

In your code where you'd want to place cookies you can check whether the user accepted your cookies with the function below that'll return true/false:

[code]
Yii::app()->cookieControl->cookiesConsent();
[/code]

You can inform cookieControl of user consent should you have your users give consent in an other way, i.e. when he logs in and accepts a cookie explicitly:

[code]
Yii::app()->cookieControl->acceptCookies(true);
[/code]

You can lose the cookieControl cookie by calling this function with the false parameter. CookieControl will then again ask for the user consent.

Change title, intro or additional (full) description by setting the variables title, intro and full in your code:

[code]
Yii::app()->cookieControl->title = '<p>My own title</p>';
Yii::app()->cookieControl->intro = '<p>My short intro why cookies are needed</p>';
Yii::app()->cookieControl->full  = '<p>And explain what cookies are</p>';
[/code]

Or in your config (/protected/config/main.php)

[code]
'cookieControl' => array(
    ...
    'title' => '<p>My own title</p>';
    'intro' => '<p>My short intro why cookies are needed</p>';
    'full'  => '<p>And explain what cookies are</p>';
),
[/code]
