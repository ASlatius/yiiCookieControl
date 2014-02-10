<?php
/**
* Yii extension wrapping the cookieControl by Civic UK
* {@link http://www.civicuk.com/cookie-law/index}
* 
* @author A. Slatius <a.slatius@gmail.com>
* @link https://github.com/Hommer101/yiiCookieControl
* @license http://www.opensource.org/licenses/mit-license.php MIT License
* @version 1.0
*/

/**
* To use this component configure it in your application (/protected/config/main.php):
* ```      
*   'cookieControl' => array(
*       'class' =>'ext.cookieControl.cookieControl',        //adjust should you place it elsewhere
*       'apikey' => '<insert your API key>',                //http://www.civicuk.com/cookie-law/cookie-configurator-v6
*       'options' => array(
*           'position'=>'CookieControl.POS_RIGHT',          //overrule the default cookieControl options here
*       )
*   ),
* ```
*
* In your controllers should include the following code
* ```
*   protected function beforeRender($view)
*   {
*       $return = parent::beforeRender($view);
*       if (Yii::app()->cookieControl->renderOnConsent())
*       {
*           // add code here that'll place cookies, ie:
*           Yii::app()->googleAnalytics->render();
*           Yii::app()->piwik->render();
*           Yii::app()->clientScript->registerScriptFile('<cookie dependent script>');
*           Yii::app()->clientScript->registerScript('somescript', '<other cookie dependent code>');
*       }
*       return $return;
*   }
* ```
* 
* In your other code where you'd want to place cookies you can check whether the user accepted 
* your cookies with the function below that'll return true/false:
* ```
*       Yii::app()->cookieControl->cookiesConsent();
* ```
* You can inform cookieControl of user consent should you have your users give consent in an 
* other way, i.e. when he logs in and accepts a cookie explicitly:
* ```
*       Yii::app()->cookieControl->acceptCookies(true);
* ```
* You can lose the cookieControl cookie by calling this function with the false parameter. 
* CookieControl will then again ask for the user consent.
*/
class cookieControl extends CApplicationComponent
{    
    /** @var string apikey to use */
    public $apikey = '';
    /** @var array user country codes that are presented with the cookie controll code, ask everyone if empty */
    public $countries = array();
    /** @var string options the config options */
    public $options = array();
    /** @var string tile */
    public $title = '';
    /** @var string tile */
    public $intro = '';
    /** @var string tile */
    public $full = '';

    /** @var boolean did the user accept the cookies? */
    private $userAccepted = false;
    /** @var json decoded cookie of cookieControl */
    private $cookieCC = null;    

    /**
    * Supported options (@20131027). See the available options at 
    * http://www.civicuk.com/cookie-law/downloads/README.html
    */
    protected $_cookieOptions = array
    (
        'cookieName'=>'yiiCookieControl',
        'cookieExpiry'=>'400',
        'consentModel'=>'CookieControl.MODEL_EXPLICIT', //MODEL_INFO, MODEL_IMPLICIT, MODEL_EXPLICIT
        'position'=>'CookieControl.POS_LEFT',           //POS_LEFT, POS_RIGHT
        'style'=>'CookieControl.STYLE_SQUARE',          //STYLE_TRIANGLE, STYLE_SQUARE, STYLE_DIAMOND
        'theme'=>'CookieControl.THEME_DARK',            //THEME_LIGHT, THEME_DARK
        'startOpen'=>'true',
        'autoHide'=>'7000',
        'subdomains'=>'true',
        'protectedCookies'=>'[]',
        'product'=>'CookieControl.PROD_FREE',
        'onAccept'=>array('ccAccepted()'),
    );
       
    /**
     * Initialize the component.
     * 
     * @throws CException if some basic requirements are not met
     */
    public function init()
    {
        /* Some basic checks.... */
        if ('' == $this->apikey) 
            throw new CException($this->t('Missing required parameter "API key" for cookieControl. '.
                                          'Get one at ').'http://www.civicuk.com/cookie-law/index');        
        if ( !function_exists('geoip_country_code_by_name') )
            throw new CException($this->t('Missing required GeoIP extension needed by cookieControl.'));
        
        /* evaluate the options */
        $this->setOptions();

        /* Explicitly ask users that have the DoNotTrack header */
        if ($this->getDntStatus())
            $this->_cookieOptions['consentModel'] = 'CookieControl.MODEL_EXPLICIT';
        
        /* Is the cookie already set? */
        $name = $this->_cookieOptions['cookieName'];
        if (isset(Yii::app()->request->cookies[$name]))
        {
            /* Cookie available, get content */
            $this->cookieCC = json_decode(Yii::app()->request->cookies[$name]->value);
            if ( (isset($this->cookieCC->consented) && 'yes' == $this->cookieCC->consented) ||
                 (isset($this->cookieCC->cm)        && 'impl' == $this->cookieCC->cm      )   )
            {
                /* We have explicit or implicit consent from user */
                $this->userAccepted = true;
            }
        }
        else 
        {
            /* determine wheter or not we need to present the question based on the users origin */
            if (count($this->countries))
            {
                $cm = $this->_cookieOptions['consentModel'];
                
                /* check remote address for match on private address range */
                if (!preg_match('/(^127\.[\d\.]{5,11})|(^192\.168\.[\d\.]{3,7})|(^10\.[\d\.]{5,11})|
                                  (^172\.1[6-9]\.[\d\.]{3,7})|(^172\.2[0-9]\.[\d\.]{3,7})|(^172\.3[0-1]\.[\d\.]{3,7})|
                                  (^::1)$/', $_SERVER['REMOTE_ADDR']))
                {
                    /* look up user location */
                    $cc = geoip_country_code_by_name($_SERVER['REMOTE_ADDR']);
                    if ( ( false === $cc                        && 
                           'CookieControl.MODEL_EXPLICIT' != $cm  ) ||
                        (!in_array($cc, $this->countries))            )
                    {
                        /* asume consent if unknown location and not explicit consent 
                         * or if not in country array 
                         */
                        $this->acceptCookies(true);
                    }
                }
                else if ('CookieControl.MODEL_EXPLICIT' != $cm)
                {
                    /* Local address, asume consent if not explicit requested */
                    $this->acceptCookies(true);
                }                
            }
        }        
    }

    /**
    * Render the cookieControl if needed 
    *     
    * @param mixed $onAccept
    * @return boolean
    */
    public function renderOnConsent($onAccept=null)
    {
        return !$this->render($onAccept);            
    }

    /**
    * Did the user accept the cookies?
    * 
    * @return boolean
    */
    public function cookiesConsent()
    {
        return $this->userAccepted;
    }
    
    /**
    * Notify cookieControl that the user accepted cookies (i.e. by logging in and checking a box)
    * Note that the cookieControl cookie will be deleted if accepted=false!
    * 
    * @param boolean cookies were accepted
    */
    public function acceptCookies($accepted=false)
    {
        $name = $this->_cookieOptions['cookieName'];
        if (true == $accepted)
        {
            /* Set a cookie indicating that the user explicitly accepted, that'll always work */
            $value = '{"pv":"","cm":"expl","consented":"yes","explicitly":"yes","hidden":"yes","open":"no"}';
            Yii::app()->request->cookies[$name] = new CHttpCookie($name, $value);
        }
        else
        {
            /* clear the cookieControl cookie */
            unset(Yii::app()->request->cookies[$name]);
        }
        $this->userAccepted = (bool)$accepted;
    }
        
    /**
    * Render the cookieControl code with the supplied options. 
    * If cookieControl rendered its code (because the user hasn't accepted the cookies) true will be returned, 
    * otherwise false (user accepted the cookies) will be returned.
    * 
    * @param array $onAccept javaScript code that will be added 
    * @return boolean true/false whether or not the cookieControl code was rendered
    */
    private function render($onAccept=null)
    {
        /* Only render cookieControl if the user accepted */
        if (!$this->cookiesConsent())
        {
            /* Set default texts should none be supplied */
            if ('' == $this->title)
                $this->title = $this->t('<p>This site uses cookies to store information on your computer.</p>');
            if ('' == $this->intro)
                $this->intro = $this->t('<p>Some of these cookies are essential to make our site work and '.
                                        'others help us to improve by giving us some insight into how '.
                                        'the site is being used.</p>');
            if ('' == $this->full)
                $this->full = $this->t('<p>These cookies are set when you submit a form, login or interact '.
                                       'with the site by doing something that goes beyond clicking some '.
                                       'simple links.</p><p>We also use some non-essential cookies to '.
                                       'anonymously track visitors or enhance your experience of this site. '.
                                       'If you\\\'re not happy with this, we won\\\'t set these cookies but some '.
                                       'nice features on the site may be unavailable.</p><p>To control third '.
                                       'party cookies, you can also <a class=\"ccc-settings\" '.
                                       'href=\"browser-settings\" target=\"_blank\">adjust your browser '.
                                       'settings.</a></p>');

            /* The script with options */
            $this->registerScripts(__CLASS__, 
                "cookieControl({t:{
                        title: '".$this->title."',
                        intro: '".$this->intro."',
                        full:'".$this->full."',
                    },
                    cookieName:'".$this->_cookieOptions['cookieName']."',
                    cookieExpiry:".$this->_cookieOptions['cookieExpiry'].",
                    position:".$this->_cookieOptions['position'].",
                    style:".$this->_cookieOptions['style'].",
                    theme:".$this->_cookieOptions['theme'].",
                    startOpen:".$this->_cookieOptions['startOpen'].",
                    autoHide:".$this->_cookieOptions['autoHide'].",
                    subdomains:".$this->_cookieOptions['subdomains'].",
                    protectedCookies: ".$this->_cookieOptions['protectedCookies'].",
                    apiKey: '".$this->apikey."',
                    product: ".$this->_cookieOptions['product'].",
                    consentModel: ".$this->_cookieOptions['consentModel'].",
                    onAccept:function(){".implode(';',$this->_cookieOptions['onAccept'])."},
                });
                function ccAccepted() {
                    ".(is_array($onAccept) ? implode('\r\n', $onAccept) : $onAccept)."
                };");
            
            return true;
        }
        return false;
    }
    
    /**
    * Get status the DNT header for the current request.
    * @return boolean whether DNT: 1 header is present
    */
    function getDntStatus() 
    {        
        return (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == 1);
    }
    
    /**
     * Publishes and registers the necessary script files.
     *
     * @param string the id of the script to be inserted into the page
     * @param string the embedded script to be inserted into the page
     */
    private function registerScripts($id, $embeddedScript)
    {
        $basePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
        $baseUrl = Yii::app()->getAssetManager()->publish($basePath);
        
        $cs = Yii::app()->clientScript;
        $cs->registerCoreScript('jquery');
        $cs->registerScriptFile("$baseUrl/cookieControl-6.2.min.js");
        $cs->registerScript($id, $embeddedScript, CClientScript::POS_END);
    }
 
    /**
    * get the configured options, validate and set
    */
    private function setOptions()
    {
        if ($this->options)
            foreach($this->options as $name => $value)
                $this->cookieOption($name,$value);
    }
    
    /**
    * Set an option to be used in cookieControl javascript 
    * 
    * @param string $name the option 
    * @param mixed $value the value
    * @throws CException on unsupported option name
    */
    private function cookieOption($name,$value)
    {
        if (in_array($name,array_keys($this->_cookieOptions)))
            $this->_cookieOptions[$name] = $value;
        else
            throw new CException($this->t('Unsupported option for cookieControl extension "'.$name.'"'));
    }
    
    /**
    * Private translate wrapper
    * 
    * @return string the (translated) text
    */
    private function t($message)
    {
        return Yii::t(__class__,$message);  
    }
            
}