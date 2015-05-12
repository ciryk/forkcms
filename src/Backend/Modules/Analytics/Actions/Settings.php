<?php

namespace Backend\Modules\Analytics\Actions;

use Backend\Core\Engine\Base\ActionIndex;
use Backend\Core\Engine\Form;
use Backend\Core\Engine\Language;
use Backend\Core\Engine\Model;
use Backend\Modules\Analytics\GoogleClient\ClientFactory;

/**
 * This is the settings-action (default), it will be used to couple your analytics
 * account
 *
 * @author Wouter Sioen <wouter@sumocoders.be>
 */
final class Settings extends ActionIndex
{
    /**
     * The form instance
     *
     * @var Form
     */
    private $form;

    /**
     * Execute the action
     */
    public function execute()
    {
        parent::execute();

        $this->loadForm();
        $this->validateForm();
        $this->parse();
        $this->display();
    }

    public function loadForm()
    {
        $this->form = new Form('settings');

        // we don't even have a secret file yet, let the user upload it
        if (Model::getModuleSetting($this->getModule(), 'secret_file') === null) {
            $this->form->addFile('secret_file');

            return;
        }

        // we don't have a token: redirect the user to Google to grant access
        if (Model::getModuleSetting($this->getModule(), 'token') === null) {
            $client = ClientFactory::createClient();

            if ($this->getParameter('code') === null) {
                $this->redirect($client->createAuthUrl());
            } else {
                $client->authenticate($this->getParameter('code'));
                Model::setModuleSetting($this->getModule(), 'token', $client->getAccessToken());
                $this->redirect(Model::createURLForAction('Settings'));
            }

            return;
        }

        // we are authenticated! Let's see which account the user wants to use
        if (Model::getModuleSetting($this->getModule(), 'account') === null) {
            $analytics = ClientFactory::createAnalyticsService();
            $accounts = $analytics->management_accounts->listManagementAccounts();
            $accountsForDropdown = array();
            foreach ($accounts->getItems() as $account) {
                $accountsForDropdown[$account->getId()] = $account->getName();
            }
            $this->form->addDropdown('account', $accountsForDropdown);

            return;
        }

        // we have an account, but don't know which property to track
        if (Model::getModuleSetting($this->getModule(), 'web_property_id') === null) {
            $analytics = ClientFactory::createAnalyticsService();
            $properties = $analytics->management_webproperties
                ->listManagementWebproperties(Model::getModuleSetting($this->getModule(), 'account'))
            ;
            $propertiesForDropdown = array();
            foreach ($properties->getItems() as $property) {
                $propertiesForDropdown[$property->getId()] = $property->getName();
            }
            $this->form->addDropdown('web_property_id', $propertiesForDropdown);

            return;
        }
    }

    protected function parse()
    {
        parent::parse();

        $this->form->parse($this->tpl);
        $this->tpl->assign(
            'web_property_id',
            Model::getModuleSetting($this->getModule(), 'web_property_id')
        );
    }

    private function validateForm()
    {
        if ($this->form->isSubmitted()) {
            if ($this->form->existsField('secret_file')) {
                $this->validateSecretFileForm();
            }

            if ($this->form->existsField('account')) {
                $this->validateAccountForm();
            }

            if ($this->form->existsField('web_property_id')) {
                $this->validatePropertyForm();
            }
        }
    }

    private function validateSecretFileForm()
    {
        $fileField = $this->form->getField('secret_file');

        if ($fileField->isFilled(Language::err('FieldIsRequired'))) {
            $fileField->isAllowedExtension(
                array('json'),
                Language::err('JsonOnly')
            );
        }

        if ($this->form->isCorrect()) {
            $fileField->moveFile(
                BACKEND_CACHE_PATH . '/' . $this->getModule() . '/'
                . $fileField->getFileName()
            );
            Model::setModuleSetting(
                $this->getModule(),
                'secret_file',
                $fileField->getFileName()
            );

            $this->redirect(Model::createURLForAction('Settings'));
        }
    }

    private function validateAccountForm()
    {
        $accountField = $this->form->getField('account');
        $accountField->isFilled(Language::err('FieldIsRequired'));

        if ($this->form->isCorrect()) {
            Model::setModuleSetting(
                $this->getModule(),
                'account',
                $accountField->getValue()
            );

            $this->redirect(Model::createURLForAction('Settings'));
        }
    }

    private function validatePropertyForm()
    {
        $webPropertyField = $this->form->getField('web_property_id');
        $webPropertyField->isFilled(Language::err('FieldIsRequired'));

        if ($this->form->isCorrect()) {
            Model::setModuleSetting(
                $this->getModule(),
                'web_property_id',
                $webPropertyField->getValue()
            );

            $this->redirect(Model::createURLForAction('Settings'));
        }
    }
}
