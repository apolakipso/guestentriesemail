<?php
namespace Craft;

class GuestEntriesEmailPlugin extends BasePlugin {

	protected $_template = 'guestentriesemail/email';
	protected $_subject = '{{handle|t}}: {{entry.title}}';

	public function init() {
		craft()->on('guestEntries.onSuccess', function(GuestEntriesEvent $event) {
			$entryModel = $event->params['entry'];
			$section = craft()->sections->getSectionById($entryModel['attributes']['sectionId']);
			$entryTypeHandle = $entryModel->getType()->handle;
			$settings = craft()->plugins->getPlugin('guestentriesemail')->getSettings()->attributes;

			// check most specific first, e.g. inquiries_appointment
			$handle = $section->handle . '_' . $entryTypeHandle;

			// falls back to section only, e.g. inquiries
			if (!isset($settings['sendEmail'][$handle])) {
				$handle = $section->handle;
			}

			$emailSubject = $settings['emailSubject'][$handle];
			$emailAddresses = $settings['emailAddresses'][$handle];
			$emailTemplate = $settings['emailTemplate'][$handle];

			if (empty($emailTemplate)) {
				$emailTemplate = $this->_template;
			}

			if (empty($emailSubject)) {
				$emailSubject = $this->_subject;
			}

			// send email notification
			if (
				$settings['sendEmail'][$handle] === '1' &&
				!empty($emailAddresses) &&
				$event->isValid == true
			) {
				$data = [
					'handle' => $handle,
					'entry' => $entryModel,
				];
				$sendToEmails = array_map('trim', explode(',', $emailAddresses));

				foreach ($sendToEmails as $value) {
					$email = new EmailModel();
					$email->toEmail = $value;
					$email->subject = craft()->templates->renderString($emailSubject, $data);
					$body = craft()->templates->render($emailTemplate, $data);
					$email->htmlBody = $body;
					$email->body = strip_tags($body);

					// Force a silent fail - the entry is saved already,
					// so while not ideal, we might as well get on with it.
					// @see http://craftcms.stackexchange.com/a/255
					try {
						craft()->email->sendEmail($email);
					} catch (\Exception $e) {
						self::log('Error sending email: ' . $e->getMessage());
						break;
					}
				}
			}
		});
	}

	public function getSettingsHtml() {
		$guestEntriesPlugin = craft()->plugins->getPlugin('guestentries', true);
		$guestEntriesPluginInstalled = $guestEntriesPlugin !== NULL;
		$editableSections = [];
		$allSections = craft()->sections->getAllSections();

		foreach ($allSections as $section) {
			// No sense in doing this for singles.
			if ($section->type !== 'single') {
				// Adds a row for the section
				$editableSections[$section->handle] = ['section' => $section];

				// Adds a row for each specific entry type
				foreach ($section->getEntryTypes() as $entryType) {
					$handle = $section->handle . '_' . $entryType->handle;
					$editableSections[$handle] = ['section' => $section, 'entryType' => $entryType];
				}
			}
		}

		// output settings template
		return craft()->templates->render('guestentriesemail/settings', [
			'settings' => $this->getSettings(),
			'editableSections' => $editableSections,
			'guestEntriesPluginInstalled' => $guestEntriesPluginInstalled,
		]);
	}

	protected function defineSettings() {
		return [
			'emailAddresses' => AttributeType::Mixed,
			'emailSubject' => AttributeType::Mixed,
			'emailTemplate' => AttributeType::Mixed,
			'sendEmail' => AttributeType::Mixed,
		];
	}

	public function getName() {
		return Craft::t('Guest Entries Email Notification');
	}

	public function getVersion() {
		return '0.1.2';
	}

	public function getDeveloper() {
		return 'Apola Kipso, Will Browar';
	}

	public function getDeveloperUrl() {
		return 'https://github.com/apolakipso';
	}

	public function hasCpSection() {
		return false;
	}
}
