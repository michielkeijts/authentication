<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Auth\Authentication;

use Cake\Core\App;
use Cake\Core\Exception\Exception;
use Cake\Core\InstanceConfigTrait;
use Auth\Authentication\AuthenticateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication Service
 */
class AuthenticationService
{
    use InstanceConfigTrait;

    /**
     * Authenticator objects
     *
     * @var array
     */
    protected $_authenticators = [];

    /**
     * Default configuration
     *
     * - `authenticate` - An array of authentication objects to use for authenticating users.
     *   You can configure multiple adapters and they will be checked sequentially
     *   when users are identified.
     *
     *   ```
     *   $service = new AuthenticationService([
     *      'Form' => [
     *         'userModel' => 'Users.Users'
     *      ]
     *   ]);
     *   ```
     *
     *   Using the class name without 'Authenticate' as the key, you can pass in an
     *   array of config for each authentication object. Additionally you can define
     *   config that should be set to all authentications objects using the 'all' key:
     *
     *   ```
     *   $service->config('authenticate', [
     *       AuthComponent::ALL => [
     *          'userModel' => 'Users.Users',
     *          'scope' => ['Users.active' => 1]
     *      ],
     *     'Form',
     *     'Basic'
     *   ]);
     *   ```
     *
     * @var array
     */
    protected $_defaultConfig = [
        'authenticators'
    ];

    /**
     * Constructor
     *
     * @var array $config Configuration options.
     */
    public function __construct(array $config = [])
    {
        $this->config($config);
        $this->loadAuthenticators();
    }

    /**
     * Loads all configured authenticators.
     *
     * @return void
     */
    public function loadAuthenticators()
    {
        if (empty($this->_config['authenticators'])) {
            return null;
        }

        foreach ($this->_config['authenticators'] as $name => $config) {
            if (is_int($name) && is_string($config)) {
                $name = $config;
                $config = [];
            }
            $this->loadAuthenticator($name, $config);
        }
    }

    /**
     * Loads an authenticator.
     *
     * @param string $name Name or class name.
     * @param array $config Authenticator configuration.
     */
    public function loadAuthenticator($name, array $config = [])
    {
        $className = $this->_getAuthenticatorClass($name, $config);
        $authenticator = new $className($config);

        if (!$authenticator instanceof AuthenticateInterface) {
            throw new Exception('Authenticator must implement AuthenticateInterface.');
        }

        if (isset($this->_authenticators)) {
            $this->_authenticators[$name] = $authenticator;
        }

        return $authenticator;
    }

    /**
     * Gets the authenticator class name.
     *
     * @param string $name Authenticator name.
     * @param array $config Configuration options for the authenticator.
     * @return string
     */
    protected function _getAuthenticatorClass($name, $config)
    {
        if (!empty($config['className'])) {
            $class = $config['className'];
            unset($config['className']);
        } else {
            $class = $name;
        }

        if (class_exists($class)) {
            return $class;
        }

        $className = App::className($class, 'Authentication', 'Authenticator');
        if (!class_exists($className)) {
            throw new Exception(sprintf('Authentication adapter "%s" was not found.', $className));
        }

        return $className;
    }

    /**
     * Callable implementation for the middleware stack.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param callable $next The next middleware to call.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function authenticate(ServerRequestInterface $request, ResponseInterface $response)
    {
        foreach ($this->_authenticators as $authenticator) {
            $user = $authenticator->authenticate($request, $response);
            if ($user) {

                break;
            }
        }
    }
}