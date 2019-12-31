<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Service;

use Laminas\Mvc\Service\RoutePluginManagerFactory;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\Mvc\Service\TranslatorServiceFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit_Framework_TestCase as TestCase;

class TranslatorServiceFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->factory = new TranslatorServiceFactory();
        $this->services = new ServiceManager();
        $this->services->setService(
            'TranslatorPluginManager',
            $this->getMock('Laminas\I18n\Translator\LoaderPluginManager')
        );
    }

    public function testReturnsMvcTranslatorWithTranslatorInterfaceServiceComposedWhenPresent()
    {
        $i18nTranslator = $this->getMock('Laminas\I18n\Translator\TranslatorInterface');
        $this->services->setService('Laminas\I18n\Translator\TranslatorInterface', $i18nTranslator);

        $translator = $this->factory->createService($this->services);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertSame($i18nTranslator, $translator->getTranslator());
    }

    public function testReturnsMvcTranslatorWithDummyTranslatorComposedWhenExtIntlIsNotAvailable()
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('This test will only run if ext/intl is not present');
        }

        $translator = $this->factory->createService($this->services);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertInstanceOf('Laminas\Mvc\I18n\DummyTranslator', $translator->getTranslator());
    }

    public function testReturnsMvcTranslatorWithI18nTranslatorComposedWhenNoTranslatorInterfaceOrConfigServicesPresent()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('This test will only run if ext/intl is present');
        }

        $translator = $this->factory->createService($this->services);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertInstanceOf('Laminas\I18n\Translator\Translator', $translator->getTranslator());
    }

    public function testReturnsTranslatorBasedOnConfigurationWhenNoTranslatorInterfaceServicePresent()
    {
        $config = array('translator' => array(
            'locale' => 'en_US',
        ));
        $this->services->setService('Config', $config);

        $translator = $this->factory->createService($this->services);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertInstanceOf('Laminas\I18n\Translator\Translator', $translator->getTranslator());

        return array(
            'translator' => $translator->getTranslator(),
            'services'   => $this->services,
        );
    }

    /**
     * In this test, we check to make sure that the TranslatorServiceFactory
     * correctly passes the LoaderPluginManager from the service locator into
     * the new Translator. This functionality is required so modules can add
     * their own translation loaders via config.
     *
     * @group 6244
     */
    public function testSetsPluginManagerFromServiceLocatorBasedOnConfiguration()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('This test will only run if ext/intl is present');
        }

        //minimum bootstrap
        $applicationConfig = array(
            'module_listener_options' => array(),
            'modules' => array(),
        );
        $serviceLocator = new ServiceManager(new ServiceManagerConfig());
        $serviceLocator->setService('ApplicationConfig', $applicationConfig);
        $serviceLocator->get('ModuleManager')->loadModules();
        $serviceLocator->get('Application')->bootstrap();

        //enable to re-write Config
        $ref = new \ReflectionObject($serviceLocator);
        $prop = $ref->getProperty('allowOverride');
        $prop->setAccessible(true);
        $prop->setValue($serviceLocator, true);

        $config = array(
            'di' => array(),
            'translator' => array(
                'locale' => 'en_US',
            ),
        );

        $serviceLocator->setService('Config', $config);

        $translator = $this->factory->createService($serviceLocator);

        $this->assertEquals(
            $serviceLocator->get('TranslatorPluginManager'),
            $translator->getPluginManager()
        );
    }

    public function testReturnsTranslatorBasedOnConfigurationWhenNoTranslatorInterfaceServicePresentWithMinimumBootstrap()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('This test will only run if ext/intl is present');
        }

        //minimum bootstrap
        $applicationConfig = array(
            'module_listener_options' => array(),
            'modules' => array(),
        );
        $serviceLocator = new ServiceManager(new ServiceManagerConfig());
        $serviceLocator->setService('ApplicationConfig', $applicationConfig);
        $serviceLocator->get('ModuleManager')->loadModules();
        $serviceLocator->get('Application')->bootstrap();

        //enable to re-write Config
        $ref = new \ReflectionObject($serviceLocator);
        $prop = $ref->getProperty('allowOverride');
        $prop->setAccessible(true);
        $prop->setValue($serviceLocator, true);

        $config = array(
            'di' => array(),
            'translator' => array(
                'locale' => 'en_US',
            ),
        );

        $serviceLocator->setService('Config', $config);

        //#5959
        //get any plugins with AbstractPluginManagerFactory
        $routePluginManagerFactory = new RoutePluginManagerFactory;
        $routePluginManager = $routePluginManagerFactory->createService($serviceLocator);

        $translator = $this->factory->createService($serviceLocator);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertInstanceOf('Laminas\I18n\Translator\Translator', $translator->getTranslator());
    }

    /**
     * @depends testReturnsTranslatorBasedOnConfigurationWhenNoTranslatorInterfaceServicePresent
     */
    public function testSetsInstantiatedI18nTranslatorInstanceInServiceManager($dependencies)
    {
        $translator = $dependencies['translator'];
        $services   = $dependencies['services'];
        $this->assertTrue($services->has('Laminas\I18n\Translator\TranslatorInterface'));
        $this->assertSame($translator, $services->get('Laminas\I18n\Translator\TranslatorInterface'));
    }

    public function testPrefersTranslatorInterfaceImplementationOverConfig()
    {
        $config = array('translator' => array(
            'locale' => 'en_US',
        ));
        $this->services->setService('Config', $config);

        $i18nTranslator = $this->getMock('Laminas\I18n\Translator\TranslatorInterface');
        $this->services->setService('Laminas\I18n\Translator\TranslatorInterface', $i18nTranslator);

        $translator = $this->factory->createService($this->services);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertSame($i18nTranslator, $translator->getTranslator());
    }

    public function testReturnsDummyTranslatorWhenTranslatorConfigIsBooleanFalse()
    {
        $config = array('translator' => false);
        $this->services->setService('Config', $config);
        $translator = $this->factory->createService($this->services);
        $this->assertInstanceOf('Laminas\Mvc\I18n\Translator', $translator);
        $this->assertInstanceOf('Laminas\Mvc\I18n\DummyTranslator', $translator->getTranslator());
    }
}
