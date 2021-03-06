<?php
/**
 * BaconAuthentication
 *
 * @link      http://github.com/Bacon/BaconAuthentication For the canonical source repository
 * @copyright 2013 Ben Scholzen 'DASPRiD'
 * @license   http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace BaconAuthenticationTest;

use BaconAuthentication\AuthenticationService;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @covers BaconAuthentication\AuthenticationService
 */
class AuthenticationServiceTest extends TestCase
{
    public function testAddInvalidPlugin()
    {
        $this->setExpectedException(
            'BaconAuthentication\Exception\InvalidArgumentException',
            'stdClass does not implement any known plugin interface'
        );

        $service = new AuthenticationService();
        $service->addPlugin(new \stdClass());
    }

    public function testAddEventAwarePlugin()
    {
        $service = new AuthenticationService();

        $plugin = $this->getMock('BaconAuthentication\Plugin\EventAwarePluginInterface');
        $plugin->expects($this->once())
               ->method('attachToEvents')
               ->with($this->equalTo($service->getEventManager()));

        $service->addPlugin($plugin);
    }

    public function testAuthenticateWithoutResult()
    {
        $this->setExpectedException(
            'BaconAuthentication\Exception\RuntimeException',
            'No plugin was able to generate a result'
        );

        $service = new AuthenticationService();
        $service->authenticate(
            $this->getMock('Zend\Stdlib\RequestInterface'),
            $this->getMock('Zend\Stdlib\ResponseInterface')
        );
    }

    public function testPreAuthenticateShortCircuit()
    {
        $result  = $this->getMock('BaconAuthentication\Result\ResultInterface');
        $service = new AuthenticationService();
        $service->getEventManager()->attach(
            'authenticate.pre',
            function () use ($result) {
                return $result;
            }
        );

        $this->assertSame(
            $result,
            $service->authenticate(
                $this->getMock('Zend\Stdlib\RequestInterface'),
                $this->getMock('Zend\Stdlib\ResponseInterface')
            )
        );
    }

    public function testPostAuthenticateShortCircuit()
    {
        $result  = $this->getMock('BaconAuthentication\Result\ResultInterface');
        $service = new AuthenticationService();
        $service->getEventManager()->attach(
            'authenticate.post',
            function () use ($result) {
                return $result;
            }
        );

        $this->assertSame(
            $result,
            $service->authenticate(
                $this->getMock('Zend\Stdlib\RequestInterface'),
                $this->getMock('Zend\Stdlib\ResponseInterface')
            )
        );
    }

    public function testChallengeIsGeneratedWithoutResult()
    {
        $service = new AuthenticationService();
        $plugin  = $this->getMock('BaconAuthentication\Plugin\ChallengePluginInterface');
        $plugin->expects($this->once())
               ->method('challenge')
               ->will($this->returnValue(true));

        $service->addPlugin($plugin);
        $result = $service->authenticate(
            $this->getMock('Zend\Stdlib\RequestInterface'),
            $this->getMock('Zend\Stdlib\ResponseInterface')
        );

        $this->assertInstanceOf('BaconAuthentication\Result\ResultInterface', $result);
        $this->assertTrue($result->isChallenge());
    }

    public function testExtractionPluginShortCircuit()
    {
        $result  = $this->getMock('BaconAuthentication\Result\ResultInterface');
        $service = new AuthenticationService();
        $plugin  = $this->getMock('BaconAuthentication\Plugin\ExtractionPluginInterface');
        $plugin->expects($this->once())
               ->method('extractCredentials')
               ->will($this->returnValue($result));

        $service->addPlugin($plugin);

        $this->assertSame(
            $result,
            $service->authenticate(
                $this->getMock('Zend\Stdlib\RequestInterface'),
                $this->getMock('Zend\Stdlib\ResponseInterface')
            )
        );
    }

    public function testNonSuccessfulExtractionSkipsAuthentication()
    {
        $service = new AuthenticationService();

        $extractionPlugin = $this->getMock('BaconAuthentication\Plugin\ExtractionPluginInterface');
        $extractionPlugin->expects($this->once())
                         ->method('extractCredentials')
                         ->will($this->returnValue(null));

        $authenticationPlugin = $this->getMock('BaconAuthentication\Plugin\AuthenticationPluginInterface');
        $authenticationPlugin->expects($this->never())
                         ->method('authenticateCredentials');

        $service->addPlugin($extractionPlugin)->addPlugin($authenticationPlugin);

        $this->setExpectedException(
            'BaconAuthentication\Exception\RuntimeException',
            'No plugin was able to generate a result'
        );
        $service->authenticate(
            $this->getMock('Zend\Stdlib\RequestInterface'),
            $this->getMock('Zend\Stdlib\ResponseInterface')
        );
    }

    public function testSuccessfulExtractionWithoutAuthenticationPlugin()
    {
        $credentials = $this->getMock('Zend\Stdlib\Parameters');
        $service     = new AuthenticationService();

        $extractionPlugin = $this->getMock('BaconAuthentication\Plugin\ExtractionPluginInterface');
        $extractionPlugin->expects($this->once())
                         ->method('extractCredentials')
                         ->will($this->returnValue($credentials));

        $service->addPlugin($extractionPlugin);

        $this->setExpectedException(
            'BaconAuthentication\Exception\RuntimeException',
            'No plugin was able to generate a result'
        );
        $service->authenticate(
            $this->getMock('Zend\Stdlib\RequestInterface'),
            $this->getMock('Zend\Stdlib\ResponseInterface')
        );
    }

    public function testExtractedCredentialsArePassedToAuthenticationPlugin()
    {
        $credentials = $this->getMock('Zend\Stdlib\Parameters');
        $result      = $this->getMock('BaconAuthentication\Result\ResultInterface');
        $service     = new AuthenticationService();

        $extractionPlugin = $this->getMock('BaconAuthentication\Plugin\ExtractionPluginInterface');
        $extractionPlugin->expects($this->once())
                         ->method('extractCredentials')
                         ->will($this->returnValue($credentials));

        $authenticationPlugin = $this->getMock('BaconAuthentication\Plugin\AuthenticationPluginInterface');
        $authenticationPlugin->expects($this->once())
                         ->method('authenticateCredentials')
                         ->with($this->equalTo($credentials))
                         ->will($this->returnValue($result));

        $service->addPlugin($extractionPlugin)->addPlugin($authenticationPlugin);

        $this->assertSame(
            $result,
            $service->authenticate(
                $this->getMock('Zend\Stdlib\RequestInterface'),
                $this->getMock('Zend\Stdlib\ResponseInterface')
            )
        );
    }

    public function testResetCredentials()
    {
        $request = $this->getMock('Zend\Stdlib\RequestInterface');

        $resetPlugin = $this->getMock('BaconAuthentication\Plugin\ResetPluginInterface');
        $resetPlugin->expects($this->once())
                    ->method('resetCredentials')
                    ->with($this->equalTo($request));

        $service = new AuthenticationService();
        $this->assertSame($service, $service->addPlugin($resetPlugin));

        $service->resetCredentials($request);
    }
}
