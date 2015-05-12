<?php

namespace Backend\Modules\Analytics\Actions;

use Backend\Core\Engine\Base\ActionIndex;
use Backend\Core\Engine\Form;
use Backend\Core\Engine\Language;
use Backend\Core\Engine\Model;

/**
 * This is the index-action (default), it will display the overview of analytics data
 *
 * @author Wouter Sioen <wouter@sumocoders.be>
 */
final class Index extends ActionIndex
{
    /**
     * The start and end timestamp of the collected data
     *
     * @var int
     */
    private $startDate;
    private $endDate;

    /**
     * @var Form
     */
    private $form;

    /**
     * Execute the action
     */
    public function execute()
    {
        parent::execute();
        $this->setDates();
        $this->loadForm();
        $this->validateForm();
        $this->parse();
        $this->display();
    }

    private function setDates()
    {
        $this->startDate = strtotime('-1 week', mktime(0, 0, 0));
        $this->endDate = mktime(0, 0, 0);
    }

    private function loadForm()
    {
        $this->form = new Form('dates');
        $this->form->addDate('start_date', $this->startDate, 'range', mktime(0, 0, 0, 1, 1, 2005), time(), 'noFocus');
        $this->form->addDate('end_date', $this->endDate, 'range', mktime(0, 0, 0, 1, 1, 2005), time(), 'noFocus');
    }

    private function validateForm()
    {
        if ($this->form->isSubmitted()) {
            $fields = $this->form->getFields();

            if (!$fields['start_date']->isFilled(Language::err('FieldIsRequired')) ||
                !$fields['end_date']->isFilled(Language::err('FieldIsRequired'))
            ) {
                return;
            }

            if (!$fields['start_date']->isValid(Language::err('DateIsInvalid')) ||
                !$fields['end_date']->isValid(Language::err('DateIsInvalid'))
            ) {
                return;
            }

            $newStartDate = Model::getUTCTimestamp($fields['start_date']);
            $newEndDate = Model::getUTCTimestamp($fields['end_date']);


            // startdate cannot be before 2005 (earliest valid google startdate)
            $valid = true;
            if ($newStartDate < mktime(0, 0, 0, 1, 1, 2005)) {
                $fields['start_date']->setError(BL::err('DateRangeIsInvalid'));
            }

            // enddate cannot be in the future
            if ($newEndDate > time()) {
                $fields['start_date']->setError(BL::err('DateRangeIsInvalid'));
            }

            // enddate cannot be before the startdate
            if ($newStartDate > $newEndDate) {
                $fields['start_date']->setError(BL::err('DateRangeIsInvalid'));
            }

            if ($this->form->isCorrect()) {
                $this->startDate = $newStartDate;
                $this->endDate = $newEndDate;
            }
        }
    }

    /**
     * Parse the datagrid and the reports
     */
    protected function parse()
    {
        parent::parse();

        $this->form->parse($this->tpl);
        $this->tpl->assign('startTimestamp', $this->startDate);
        $this->tpl->assign('endTimestamp', $this->endDate);
    }
}
