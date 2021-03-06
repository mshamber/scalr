<?php

class MailEventObserver implements IDeferredEventObserver
{
    private $Mailer;
    private $Config;
    public $ObserverName = 'Mail';

    function __construct()
    {
        $this->Mailer = \Scalr::getContainer()->mailer;
    }

    public function SetConfig($config)
    {
        $this->Config = $config;
        $this->Mailer->setTo($this->Config->GetFieldByName("EventMailTo")->Value);
    }

    public static function GetConfigurationForm()
    {
        $ConfigurationForm = new DataForm();
        $ConfigurationForm->SetInlineHelp("");

        $ConfigurationForm->AppendField( new DataFormField("IsEnabled", FORM_FIELD_TYPE::CHECKBOX, "Enabled"));
        $ConfigurationForm->AppendField( new DataFormField("EventMailTo", FORM_FIELD_TYPE::TEXT, "E-mail"));

        $ReflectionInterface = new ReflectionClass("IEventObserver");
        $events = $ReflectionInterface->getMethods();

        $ConfigurationForm->AppendField(new DataFormField("", FORM_FIELD_TYPE::SEPARATOR, "Notify about following events"));

        foreach ($events as $event)
        {
            $name = substr($event->getName(), 2);

            $ConfigurationForm->AppendField( new DataFormField(
                "{$event->getName()}Notify",
                FORM_FIELD_TYPE::CHECKBOX,
                "{$name}",
                false,
                array(),
                null,
                null,
                EVENT_TYPE::GetEventDescription($name)
                )
            );
        }

        return $ConfigurationForm;
    }

    public function __call($method, $args)
    {
        // If observer enabled
        if (!$this->Config || $this->Config->GetFieldByName("IsEnabled")->Value == 0)
            return;


        $enabled = $this->Config->GetFieldByName("{$method}Notify");
        if (!$enabled || $enabled->Value == 0)
            return;

        $DB = \Scalr::getDb();

        // Event name
        $name = substr($method, 2);

        // Event message
        $message = $DB->GetOne("SELECT message FROM events WHERE event_id = ?", array($args[0]->GetEventID()));

        $farm_name = $DB->GetOne("SELECT name FROM farms WHERE id=?", array($args[0]->GetFarmID()));

        // Set subject
        if (!$farm_name) {
            $this->Mailer->setSubject("{$name} event notification (FarmID: {$args[0]->GetFarmID()})");
        } else {
            $this->Mailer->setSubject("{$name} event notification (FarmID: {$args[0]->GetFarmID()} FarmName: {$farm_name})");
        }

        // Set body
        $this->Mailer->setMessage($message);

        // Send mail
        try {
            $res = $this->Mailer->send();
        } catch (\Exception $e) {
            $res = false;
        }
        if (!$res) {
            Logger::getLogger(__CLASS__)->info("Mail sent to '{$this->Config->GetFieldByName("EventMailTo")->Value}'. Result: {$res}");
        }
    }
}
