<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
use PrestaShop\PrestaShop\Core\Util\InternationalizedDomainNameConverter;
use Symfony\Component\HttpFoundation\IpUtils;

class AdminLoginControllerCore extends AdminController
{
    /**
     * @var InternationalizedDomainNameConverter
     */
    private $IDNConverter;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->errors = [];
        $this->display_header = false;
        $this->display_footer = false;
        $this->layout = _PS_ADMIN_DIR_ . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $this->bo_theme
            . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'login'
            . DIRECTORY_SEPARATOR . 'layout.tpl';

        if (!headers_sent()) {
            header('Login: true');
        }
        $this->IDNConverter = new InternationalizedDomainNameConverter();
    }

    public function setMedia($isNewTheme = false)
    {
        $this->addJs(_PS_JS_DIR_ . 'jquery/jquery-3.4.1.min.js');
        $this->addjqueryPlugin('validate');
        $this->addJS(_PS_JS_DIR_ . 'jquery/plugins/validate/localization/messages_' . $this->context->language->iso_code . '.js');
        $this->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/public/theme.css', 'all', 0);
        $this->addJS(_PS_JS_DIR_ . 'vendor/spin.js');
        $this->addJS(_PS_JS_DIR_ . 'vendor/ladda.js');
        Media::addJsDef(['img_dir' => _PS_IMG_]);
        Media::addJsDefL('one_error', $this->trans('There is one error.', [], 'Admin.Notifications.Error'));
        Media::addJsDefL('more_errors', $this->trans('There are several errors.', [], 'Admin.Notifications.Error'));

        Hook::exec(
            'actionAdminLoginControllerSetMedia',
            [
                'controller' => $this,
            ]
        );

        // Specific Admin Theme
        $this->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/css/overrides.css', 'all', PHP_INT_MAX);
    }

    public function initContent()
    {
        if (!Tools::usingSecureMode() && Configuration::get('PS_SSL_ENABLED')) {
            // You can uncomment these lines if you want to force https even from localhost and automatically redirect
            // header('HTTP/1.1 301 Moved Permanently');
            // header('Location: '.Tools::getShopDomainSsl(true).$_SERVER['REQUEST_URI']);
            // exit();
            $clientIsMaintenanceOrLocal = IpUtils::checkIp(Tools::getRemoteAddr(), array_merge(['127.0.0.1'], explode(',', Configuration::get('PS_MAINTENANCE_IP'))));
            // If ssl is enabled, https protocol is required. Exception for maintenance and local (127.0.0.1) IP
            if ($clientIsMaintenanceOrLocal) {
                $warningSslMessage = $this->trans('SSL is activated. However, your IP is allowed to enter unsecure mode for maintenance or local IP issues.', [], 'Admin.Login.Notification');
            } else {
                $url = 'https://' . Tools::safeOutput(Tools::getServerName()) . Tools::safeOutput($_SERVER['REQUEST_URI']);
                $warningSslMessage = $this->trans(
                    'SSL is activated. Please connect using the following link to [1]log in to secure mode (https://)[/1]',
                    ['[1]' => '<a href="' . $url . '">', '[/1]' => '</a>'],
                    'Admin.Login.Notification'
                );
            }
            $this->context->smarty->assign('warningSslMessage', $warningSslMessage);
        }

        if (file_exists(_PS_ADMIN_DIR_ . '/../install')) {
            $this->context->smarty->assign('wrong_install_name', true);
        }

        if (basename(_PS_ADMIN_DIR_) == 'admin' && file_exists(_PS_ADMIN_DIR_ . '/../admin/')) {
            $rand = sprintf(
                'admin%03d%s/',
                mt_rand(0, 999),
                Tools::strtolower(Tools::passwdGen(16))
            );
            if (@rename(_PS_ADMIN_DIR_ . '/../admin/', _PS_ADMIN_DIR_ . '/../' . $rand)) {
                Tools::redirectAdmin('../' . $rand);
            } else {
                $this->context->smarty->assign([
                    'wrong_folder_name' => true,
                ]);
            }
        } else {
            $rand = basename(_PS_ADMIN_DIR_) . '/';
        }

        $this->context->smarty->assign([
            'randomNb' => $rand,
            'adminUrl' => Tools::getCurrentUrlProtocolPrefix() . Tools::getShopDomain() . __PS_BASE_URI__ . $rand,
            'homeUrl' => Tools::getCurrentUrlProtocolPrefix() . Tools::getShopDomain() . __PS_BASE_URI__,
        ]);

        // Redirect to admin panel
        if (Tools::isSubmit('redirect') && Validate::isControllerName(Tools::getValue('redirect'))) {
            $this->context->smarty->assign('redirect', Tools::getValue('redirect'));
        } else {
            $tab = new Tab((int) $this->context->employee->default_tab);
            $this->context->smarty->assign('redirect', $this->context->link->getAdminLink($tab->class_name));
        }

        if ($nb_errors = count($this->errors)) {
            $this->context->smarty->assign([
                'errors' => $this->errors,
                'nbErrors' => $nb_errors,
                'shop_name' => Tools::safeOutput(Configuration::get('PS_SHOP_NAME')),
                'disableDefaultErrorOutPut' => true,
            ]);
        }

        if ($email = Tools::getValue('email')) {
            $this->context->smarty->assign('email', $email);
        }
        if ($password = Tools::getValue('password')) {
            $this->context->smarty->assign('password', $password);
        }

        // For reset password feature
        if ($reset_token = Tools::getValue('reset_token')) {
            $this->context->smarty->assign('reset_token', $reset_token);
        }
        if ($id_employee = Tools::getValue('id_employee')) {
            $this->context->smarty->assign('id_employee', $id_employee);
            $employee = new Employee($id_employee);
            if (Validate::isLoadedObject($employee)) {
                $this->context->smarty->assign('reset_email', $employee->email);
            }
        }

        $this->setMedia($isNewTheme = false);
        $this->initHeader();
        parent::initContent();
        $this->initFooter();

        //force to disable modals
        $this->context->smarty->assign('modals', null);
    }

    public function checkToken()
    {
        return true;
    }

    /**
     * All BO users can access the login page.
     *
     * @return bool
     */
    public function viewAccess($disable = false)
    {
        return true;
    }

    public function postProcess()
    {
        Hook::exec(
            'actionAdminLoginControllerBefore',
            [
                'controller' => $this,
            ]
        );

        if (Tools::isSubmit('submitLogin')) {
            $this->processLogin();
        } elseif (Tools::isSubmit('submitForgot')) {
            $this->processForgot();
        } elseif (Tools::isSubmit('submitReset')) {
            $this->processReset();
        }

        // No hook after because of die calls inside process methods
    }

    public function processLogin()
    {
        /* Check fields validity */
        $passwd = trim(Tools::getValue('passwd'));
        $email = $this->IDNConverter->emailToUtf8(trim(Tools::getValue('email')));
        Hook::exec(
            'actionAdminLoginControllerLoginBefore',
            [
                'controller' => $this,
                'password' => $passwd,
                'email' => $email,
            ]
        );

        if (empty($email)) {
            $this->errors[] = $this->trans('Email is empty.', [], 'Admin.Notifications.Error');
        } elseif (!Validate::isEmail($email)) {
            $this->errors[] = $this->trans('Invalid email address.', [], 'Admin.Notifications.Error');
        }

        if (empty($passwd)) {
            $this->errors[] = $this->trans('The password field is blank.', [], 'Admin.Notifications.Error');
        } elseif (!Validate::isPlaintextPassword($passwd)) {
            $this->errors[] = $this->trans('Invalid password.', [], 'Admin.Notifications.Error');
        }

        if (!count($this->errors)) {
            // Find employee
            $this->context->employee = new Employee();
            $is_employee_loaded = $this->context->employee->getByEmail($email, $passwd);
            $employee_associated_shop = $this->context->employee->getAssociatedShops();
            if (!$is_employee_loaded) {
                $this->errors[] = $this->trans('The employee does not exist, or the password provided is incorrect.', [], 'Admin.Login.Notification');
                $this->context->employee->logout();
            } elseif (empty($employee_associated_shop) && !$this->context->employee->isSuperAdmin()) {
                $this->errors[] = $this->trans('This employee does not manage the shop anymore (either the shop has been deleted or permissions have been revoked).', [], 'Admin.Login.Notification');
                $this->context->employee->logout();
            } else {
                PrestaShopLogger::addLog($this->trans('Back office connection from %ip%', ['%ip%' => Tools::getRemoteAddr()], 'Admin.Advparameters.Feature'), 1, null, '', 0, true, (int) $this->context->employee->id);

                $this->context->employee->remote_addr = (int) ip2long(Tools::getRemoteAddr());
                // Update cookie
                $cookie = Context::getContext()->cookie;
                $cookie->id_employee = $this->context->employee->id;
                $cookie->email = $this->context->employee->email;
                $cookie->profile = $this->context->employee->id_profile;
                $cookie->passwd = $this->context->employee->passwd;
                $cookie->remote_addr = $this->context->employee->remote_addr;
                $cookie->registerSession(new EmployeeSession());

                if (!Tools::getValue('stay_logged_in')) {
                    $cookie->last_activity = time();
                }

                $cookie->write();

                // If there is a valid controller name submitted, redirect to it
                if (isset($_POST['redirect']) && Validate::isControllerName($_POST['redirect'])) {
                    $url = $this->context->link->getAdminLink($_POST['redirect']);
                } else {
                    $tab = new Tab((int) $this->context->employee->default_tab);
                    $url = $this->context->link->getAdminLink($tab->class_name);
                }

                Hook::exec(
                    'actionAdminLoginControllerLoginAfter',
                    [
                        'controller' => $this,
                        'employee' => $this->context->employee,
                        'redirect' => $url,
                    ]
                );

                if (Tools::isSubmit('ajax')) {
                    die(json_encode(['hasErrors' => false, 'redirect' => $url]));
                } else {
                    $this->redirect_after = $url;
                }
            }
        }
        if (Tools::isSubmit('ajax')) {
            die(json_encode(['hasErrors' => true, 'errors' => $this->errors]));
        }
    }

    public function processForgot()
    {
        $email = $this->IDNConverter->emailToUtf8(trim(Tools::getValue('email_forgot')));
        Hook::exec(
            'actionAdminLoginControllerForgotBefore',
            [
                'controller' => $this,
                'email' => $email,
            ]
        );

        /* @phpstan-ignore-next-line */
        if (_PS_MODE_DEMO_) {
            $this->errors[] = $this->trans('This functionality has been disabled.', [], 'Admin.Notifications.Error');
        } elseif (!$email) {
            $this->errors[] = $this->trans('Email is empty.', [], 'Admin.Notifications.Error');
        } elseif (!Validate::isEmail($email)) {
            $this->errors[] = $this->trans('Invalid email address.', [], 'Admin.Notifications.Error');
        } else {
            $employee = new Employee();
            if (!$employee->getByEmail($email)) {
                $this->errors[] = $this->trans('This account does not exist.', [], 'Admin.Login.Notification');
            } elseif ((strtotime($employee->last_passwd_gen . '+' . Configuration::get('PS_PASSWD_TIME_BACK') . ' minutes') - time()) > 0) {
                $this->errors[] = $this->trans('You can reset your password every %interval% minute(s) only. Please try again later.', ['%interval%' => Configuration::get('PS_PASSWD_TIME_BACK')], 'Admin.Login.Notification');
            }
        }

        if (!count($this->errors) && isset($employee)) {
            if (!$employee->hasRecentResetPasswordToken()) {
                $employee->stampResetPasswordToken();
                $employee->update();
            }

            $admin_url = $this->context->link->getAdminLink('AdminLogin');
            $params = [
                '{email}' => $employee->email,
                '{lastname}' => $employee->lastname,
                '{firstname}' => $employee->firstname,
                '{url}' => $admin_url . '&id_employee=' . (int) $employee->id . '&reset_token=' . $employee->reset_password_token,
            ];

            $employeeLanguage = new Language((int) $employee->id_lang);

            if (
                Mail::Send(
                    $employee->id_lang,
                    'password_query',
                    $this->trans(
                        'Your new password',
                        [],
                        'Emails.Subject',
                        $employeeLanguage->locale
                    ),
                    $params,
                    $employee->email,
                    $employee->firstname . ' ' . $employee->lastname
                )
            ) {
                // Update employee only if the mail can be sent
                Shop::setContext(Shop::CONTEXT_SHOP, (int) min($employee->getAssociatedShops()));

                Hook::exec(
                    'actionAdminLoginControllerForgotAfter',
                    [
                        'controller' => $this,
                        'employee' => $employee,
                    ]
                );

                die(json_encode([
                    'hasErrors' => false,
                    'confirm' => $this->trans('Please, check your mailbox. A link to reset your password has been sent to you.', [], 'Admin.Login.Notification'),
                ]));
            } else {
                die(json_encode([
                    'hasErrors' => true,
                    'errors' => [$this->trans('An error occurred while attempting to reset your password.', [], 'Admin.Login.Notification')],
                ]));
            }
        } elseif (Tools::isSubmit('ajax')) {
            die(json_encode(['hasErrors' => true, 'errors' => $this->errors]));
        }
    }

    public function processReset()
    {
        $reset_token_value = trim(Tools::getValue('reset_token'));
        $id_employee = trim(Tools::getValue('id_employee'));
        $reset_email = $this->IDNConverter->emailToUtf8(trim(Tools::getValue('reset_email')));
        $reset_password = trim(Tools::getValue('reset_passwd'));
        $reset_confirm = trim(Tools::getValue('reset_confirm'));
        Hook::exec(
            'actionAdminLoginControllerResetBefore',
            [
                'controller' => $this,
                'reset_token_value' => $reset_token_value,
                'id_employee' => $id_employee,
                'reset_email' => $reset_email,
                'reset_password' => $reset_password,
                'reset_confirm' => $reset_confirm,
            ]
        );

        /* @phpstan-ignore-next-line */
        if (_PS_MODE_DEMO_) {
            $this->errors[] = $this->trans('This functionality has been disabled.', [], 'Admin.Notifications.Error');
        } elseif (!$reset_token_value) {
            // hidden fields
            $this->errors[] = $this->trans('Some identification information is missing.', [], 'Admin.Login.Notification');
        } elseif (!$id_employee) {
            $this->errors[] = $this->trans('Some identification information is missing.', [], 'Admin.Login.Notification');
        } elseif (!$reset_email) {
            $this->errors[] = $this->trans('Some identification information is missing.', [], 'Admin.Login.Notification');
        } elseif (!$reset_password) {
            // password (twice)
            $this->errors[] = $this->trans('The password is missing: please enter your new password.', [], 'Admin.Login.Notification');
        } elseif (!Validate::isPlaintextPassword($reset_password)) {
            $this->errors[] = $this->trans('The password is not in a valid format.', [], 'Admin.Login.Notification');
        } elseif (!$reset_confirm) {
            $this->errors[] = $this->trans('The confirmation is empty: please fill in the password confirmation as well.', [], 'Admin.Login.Notification');
        } elseif ($reset_password !== $reset_confirm) {
            $this->errors[] = $this->trans('The password and its confirmation do not match. Please double check both passwords.', [], 'Admin.Login.Notification');
        } else {
            $employee = new Employee();
            if (!$employee->getByEmail($reset_email) || $employee->id != $id_employee) { // check matching employee id with its email
                $this->errors[] = $this->trans('This account does not exist.', [], 'Admin.Login.Notification');
            } elseif ((strtotime($employee->last_passwd_gen . '+' . Configuration::get('PS_PASSWD_TIME_BACK') . ' minutes') - time()) > 0) {
                $this->errors[] = $this->trans('You can reset your password every %interval% minute(s) only. Please try again later.', ['%interval%' => Configuration::get('PS_PASSWD_TIME_BACK')], 'Admin.Login.Notification');
            } elseif ($employee->getValidResetPasswordToken() !== $reset_token_value) {
                // To update password, we must have the temporary reset token that matches.
                $this->errors[] = $this->trans('Your password reset request expired. Please start again.', [], 'Admin.Login.Notification');
            }
        }

        if (!count($this->errors) && isset($employee)) {
            $employee->passwd = $this->get('hashing')->hash($reset_password, _COOKIE_KEY_);
            $employee->last_passwd_gen = date('Y-m-d H:i:s', time());

            $params = [
                '{email}' => $employee->email,
                '{lastname}' => $employee->lastname,
                '{firstname}' => $employee->firstname,
            ];

            $employeeLanguage = new Language((int) $this->context->employee->id_lang);

            if (
                Mail::Send(
                    $employee->id_lang,
                    'password',
                    $this->trans(
                        'Your new password',
                        [],
                        'Emails.Subject',
                        $employeeLanguage->locale
                    ),
                    $params,
                    $employee->email,
                    $employee->firstname . ' ' . $employee->lastname
                )
            ) {
                // Update employee only if the mail can be sent
                Shop::setContext(Shop::CONTEXT_SHOP, (int) min($employee->getAssociatedShops()));

                $result = $employee->update();
                if (!$result) {
                    $this->errors[] = $this->trans('An error occurred while attempting to change your password.', [], 'Admin.Login.Notification');
                } else {
                    $employee->removeResetPasswordToken(); // Delete temporary reset token
                    $employee->update();

                    Hook::exec(
                        'actionAdminLoginControllerResetAfter',
                        [
                            'controller' => $this,
                            'employee' => $employee,
                        ]
                    );
                    die(json_encode([
                        'hasErrors' => false,
                        'confirm' => $this->trans('The password has been changed successfully.', [], 'Admin.Login.Notification'),
                    ]));
                }
            } else {
                die(json_encode([
                    'hasErrors' => true,
                    'errors' => [$this->trans('An error occurred while attempting to change your password.', [], 'Admin.Login.Notification')],
                ]));
            }
        } elseif (Tools::isSubmit('ajax')) {
            die(json_encode(['hasErrors' => true, 'errors' => $this->errors]));
        }
    }
}
