<?php

namespace Backend\Core\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Backend\Core\Engine\Model as BackendModel;
use Backend\Modules\Users\Engine\Model as BackendUsersModel;

/**
 * The class below will handle all authentication stuff. It will handle module-access, action-access, ...
 */
class Authentication
{
    /**
     * All allowed modules
     *
     * @var    array
     */
    private static $allowedActions = array();

    /**
     * All allowed modules
     *
     * @var    array
     */
    private static $allowedModules = array();

    /**
     * A user object for the current authenticated user
     *
     * @var    User
     */
    private static $user;

    /**
     * Check the strength of the password
     *
     * @param string $password The password.
     *
     * @return string
     */
    public static function checkPassword($password)
    {
        // init vars
        $score = 0;
        $uniqueChars = array();

        // less then 4 chars is just a weak password
        if (mb_strlen($password) <= 4) {
            return 'weak';
        }

        // loop chars and add unique chars
        $passwordChars = str_split($password);
        foreach ($passwordChars as $char) {
            $uniqueChars[$char] = $char;
        }

        // less then 3 unique chars is just weak
        if (count($uniqueChars) < 3) {
            return 'weak';
        }

        // more then 6 chars is good
        if (mb_strlen($password) >= 6) {
            ++$score;
        }

        // more then 8 is better
        if (mb_strlen($password) >= 8) {
            ++$score;
        }

        // @todo
        // upper and lowercase?
        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) {
            $score += 2;
        }

        // number?
        if (preg_match('/\d+/', $password)) {
            ++$score;
        }

        // special char?
        if (preg_match('/.[!,@,#,$,%,^,&,*,?,_,~,-,(,)]/', $password)) {
            ++$score;
        }

        // strong password
        if ($score >= 4) {
            return 'strong';
        }

        // ok
        if ($score >= 2) {
            return 'average';
        }

        // fallback
        return 'weak';
    }

    /**
     * Cleanup sessions for the current user and sessions that are invalid
     */
    public static function cleanupOldSessions()
    {
        // remove all sessions that are invalid (older then 30 min)
        BackendModel::get('database')->delete('users_sessions', 'date <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
    }

    /**
     * Returns the encrypted password for a user by giving a email/password
     * Returns false if no user was found for this user/pass combination
     *
     * @param string $email    The email.
     * @param string $password The password.
     *
     * @return string
     */
    public static function getEncryptedPassword($email, $password)
    {
        $email = (string) $email;
        $password = (string) $password;

        // fetch user ID by email
        $userId = BackendUsersModel::getIdByEmail($email);

        // check if a user ID was found, return false if no user exists
        if ($userId === false) {
            return false;
        }

        // fetch user record
        $user = new User($userId);
        $key = $user->getSetting('password_key');

        // return the encrypted string
        return (string) self::getEncryptedString($password, $key);
    }

    /**
     * Returns a string encrypted like sha1(md5($salt) . md5($string))
     *    The salt is an optional extra string you can strengthen your encryption with
     *
     * @param string $string The string to encrypt.
     * @param string $salt   The salt to use.
     *
     * @return string
     */
    public static function getEncryptedString($string, $salt = null)
    {
        $string = (string) $string;
        $salt = (string) $salt;

        // return the encrypted string
        return (string) sha1(md5($salt) . md5($string));
    }

    /**
     * Returns the current authenticated user
     *
     * @return User
     */
    public static function getUser()
    {
        // if the user-object doesn't exist create a new one
        if (self::$user === null) {
            self::$user = new User();
        }

        return self::$user;
    }

    /**
     * @return array
     */
    public static function getAllowedActions()
    {
        // we will cache everything
        if (!empty(self::$allowedActions)) {
            return self::$allowedActions;
        }

        // get allowed actions
        $allowedActionsRows = (array) BackendModel::get('database')->getRecords(
            'SELECT gra.module, gra.action, MAX(gra.level) AS level
            FROM users_sessions AS us
            INNER JOIN users AS u ON us.user_id = u.id
            INNER JOIN users_groups AS ug ON u.id = ug.user_id
            INNER JOIN groups_rights_actions AS gra ON ug.group_id = gra.group_id
            WHERE us.session_id = ? AND us.secret_key = ?
            GROUP BY gra.module, gra.action',
            array(\SpoonSession::getSessionId(), \SpoonSession::get('backend_secret_key'))
        );

        // add all actions and their level
        $modules = BackendModel::getModules();
        foreach ($allowedActionsRows as $row) {
            // add if the module is installed
            if (in_array(
                $row['module'],
                $modules
            )
            ) {
                self::$allowedActions[$row['module']][$row['action']] = (int) $row['level'];
            }
        }

        return self::$allowedActions;
    }

    /**
     * Is the given action allowed for the current user
     *
     * @param string $action The action to check for.
     * @param string $module The module wherein the action is located.
     *
     * @return bool
     */
    public static function isAllowedAction($action = null, $module = null)
    {
        $alwaysAllowed = self::getAlwaysAllowed();

        // grab the URL from the reference
        $url = BackendModel::get('url');

        $action = ($action !== null) ? (string) $action : $url->getAction();
        $module = \SpoonFilter::toCamelCase(($module !== null) ? (string) $module : $url->getModule());

        // is this action an action that doesn't require authentication?
        if (isset($alwaysAllowed[$module][$action])) {
            return true;
        }

        // users that aren't logged in can only access always allowed items
        if (!self::isLoggedIn()) {
            return false;
        }

        // get modules
        $modules = BackendModel::getModules();

        // module exists and God user is enough to be allowed
        if (in_array($module, $modules) && self::getUser()->isGod()) {
            return true;
        }

        $allowedActions = self::getAllowedActions();

        // do we know a level for this action
        if (isset($allowedActions[$module][$action])) {
            // is the level greater than zero? aka: do we have access?
            if ((int) $allowedActions[$module][$action] > 0) {
                return true;
            }
        }

        // fallback
        return false;
    }

    /**
     * @return array
     */
    private static function getAlwaysAllowed()
    {
        // always allowed actions (yep, hardcoded, because we don't want other people to fuck up)
        return array(
            'Core' => array('GenerateUrl' => 7, 'ContentCss' => 7, 'Templates' => 7),
            'Error' => array('Index' => 7),
            'Authentication' => array('Index' => 7, 'ResetPassword' => 7, 'Logout' => 7),
        );
    }

    /**
     * Is the given module allowed for the current user
     *
     * @param string $module The module to check for.
     *
     * @return bool
     */
    public static function isAllowedModule($module)
    {
        $modules = BackendModel::getModules();
        $alwaysAllowed = array('Core', 'Error', 'Authentication');
        $module = \SpoonFilter::toCamelCase((string) $module);

        // is this module a module that doesn't require user level authentication?
        if (in_array($module, $alwaysAllowed)) {
            return true;
        }

        // users that aren't logged in can only access always allowed items
        if (!self::isLoggedIn()) {
            return false;
        }

        // module is active and God user, good enough
        if (in_array($module, $modules) && self::getUser()->isGod()) {
            return true;
        }

        // do we already know something?
        if (empty(self::$allowedModules)) {
            // init var
            $db = BackendModel::get('database');

            // get allowed modules
            $allowedModules = $db->getColumn(
                'SELECT DISTINCT grm.module
                 FROM users_sessions AS us
                 INNER JOIN users AS u ON us.user_id = u.id
                 INNER JOIN users_groups AS ug ON u.id = ug.user_id
                 INNER JOIN groups_rights_modules AS grm ON ug.group_id = grm.group_id
                 WHERE us.session_id = ? AND us.secret_key = ?',
                array(\SpoonSession::getSessionId(), \SpoonSession::get('backend_secret_key'))
            );

            // add all modules
            foreach ($allowedModules as $row) {
                self::$allowedModules[$row] = true;
            }
        }

        // not available in our cache
        if (!isset(self::$allowedModules[$module])) {
            return false;
        } else {
            // return value that was stored in cache
            return self::$allowedModules[$module];
        }
    }

    /**
     * Is the current user logged in?
     *
     * @return bool
     */
    public static function isLoggedIn()
    {
        if (BackendModel::getContainer()->has('logged_in')) {
            return BackendModel::getContainer()->get('logged_in');
        }

        // check if all needed values are set in the session
        // @todo could be written by SpoonSession::get (since that no longer throws exceptions)
        if (\SpoonSession::exists('backend_logged_in', 'backend_secret_key') &&
            (bool) \SpoonSession::get('backend_logged_in') &&
            (string) \SpoonSession::get('backend_secret_key') != ''
        ) {
            // get database instance
            $db = BackendModel::get('database');

            // get the row from the tables
            $sessionData = $db->getRecord(
                'SELECT us.id, us.user_id
                 FROM users_sessions AS us
                 WHERE us.session_id = ? AND us.secret_key = ?
                 LIMIT 1',
                array(\SpoonSession::getSessionId(), \SpoonSession::get('backend_secret_key'))
            );

            // if we found a matching row, we know the user is logged in, so we update his session
            if ($sessionData !== null) {
                // update the session in the table
                $db->update(
                    'users_sessions',
                    array('date' => BackendModel::getUTCDate()),
                    'id = ?',
                    (int) $sessionData['id']
                );

                // create a user object, it will handle stuff related to the current authenticated user
                self::$user = new User($sessionData['user_id']);

                // the user is logged on
                BackendModel::getContainer()->set('logged_in', true);

                return true;
            }
        }

        // no data found, so fuck up the session, will be handled later on in the code
        \SpoonSession::set('backend_logged_in', false);
        BackendModel::getContainer()->set('logged_in', false);
        \SpoonSession::set('backend_secret_key', '');

        return false;
    }

    /**
     * Login the user with the given credentials.
     * Will return a boolean that indicates if the user is logged in.
     *
     * @param string $login    The users login.
     * @param string $password The password provided by the user.
     *
     * @return bool
     */
    public static function loginUser($login, $password)
    {
        $login = (string) $login;
        $password = (string) $password;

        $db = BackendModel::get('database');

        // fetch the encrypted password
        $passwordEncrypted = static::getEncryptedPassword($login, $password);

        // check in database (is the user active and not deleted, are the email and password correct?)
        $userId = (int) $db->getVar(
            'SELECT u.id
             FROM users AS u
             WHERE u.email = ? AND u.password = ? AND u.active = ? AND u.deleted = ?
             LIMIT 1',
            array($login, $passwordEncrypted, 'Y', 'N')
        );

        // not 0 = valid user!
        if ($userId !== 0) {
            // cleanup old sessions
            self::cleanupOldSessions();

            // build the session array (will be stored in the database)
            $session = array();
            $session['user_id'] = $userId;
            $session['secret_key'] = static::getEncryptedString(\SpoonSession::getSessionId(), $userId);
            $session['session_id'] = \SpoonSession::getSessionId();
            $session['date'] = BackendModel::getUTCDate();

            // insert a new row in the session-table
            $db->insert('users_sessions', $session);

            // store some values in the session
            \SpoonSession::set('backend_logged_in', true);
            \SpoonSession::set('backend_secret_key', $session['secret_key']);

            // update/instantiate the value for the logged_in container.
            BackendModel::getContainer()->set('logged_in', true);
            self::$user = new User($userId);

            // return result
            return true;
        } else {
            // userId 0 will not exist, so it means that this isn't a valid combination
            // reset values for invalid users. We can't destroy the session
            // because session-data can be used on the site.
            \SpoonSession::set('backend_logged_in', false);
            \SpoonSession::set('backend_secret_key', '');

            // update/instantiate the value for the logged_in container.
            BackendModel::getContainer()->set('logged_in', false);

            // return result
            return false;
        }
    }

    /**
     * Logout the current user
     */
    public static function logout()
    {
        // remove all rows owned by the current user
        BackendModel::get('database')->delete('users_sessions', 'session_id = ?', \SpoonSession::getSessionId());

        // reset values. We can't destroy the session because session-data can be used on the site.
        \SpoonSession::set('backend_logged_in', false);
        \SpoonSession::set('backend_secret_key', '');
        \SpoonSession::set('csrf_token', '');
    }

    /**
     * Reset our class to make sure no contamination from previous
     * authentications persists. This signifies a deeper issue with
     * this class. Solving the issue would be preferable to introducting
     * another method. This currently only exists to serve the test.
     */
    public static function tearDown()
    {
        self::$allowedActions = array();
        self::$allowedModules = array();
        self::$user = null;
    }
}
