<?php
namespace Bart\GitHook;

use Bart\Diesel;
use Bart\Git;
use Bart\Git\Commit;
use chobie\Jira\Api\Authentication\Basic;

/**
 * Adds comment to JIRA
 */
class JiraLabel extends GitHookAction
{
	/** @var \chobie\Jira\Api */
	private $jiraClient;
	private static $labels = ['FeatureFlip', 'FF'];

	/**
	 * Jira Label Hook Action
	 */
	public function __construct()
	{
		parent::__construct();

		/** @var \Bart\Jira\JiraClientConfig $jConfigs */
		$jConfigs = Diesel::create('\Bart\Jira\JiraClientConfig');

		$this->jiraClient = Diesel::create('\chobie\Jira\Api',
			$jConfigs->baseURL(), new Basic($jConfigs->username(), $jConfigs->password()));
	}

	/**
	 * Add a comment in JIRA with the commit hash
	 * @param Commit $commit The commit for which we're running the Git Hook
	 * @throws GitHookException if requirement fails
	 */
	public function run(Commit $commit)
	{
		$jiraIssues = $commit->jiras();
		$this->logger->debug('Found ' . count($jiraIssues) . " jira issue(s) in $commit");
		$revision = $commit->revision();
		if ($this->isChangeFeatureFlip($revision)) {
			foreach ($jiraIssues as $jira) {
				$this->logger->debug("Adding comment to jira {$jira}");
				$this->addLabels($jira);
			}
		}
	}

	/**
	 * @param string $revision
	 * @return bool
	 */
	private function isChangeFeatureFlip($revision)
	{
		/** @var Git $git */
		$git = Diesel::create('\Bart\Git');
		$fileList = $git->get_file_list($revision);
		foreach ($fileList as $file) {
			if ($file == 'conf_override/features.conf') {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param $jira
	 */
	private function addLabels($jira)
	{
		$params = [
			'fields' => [
				'labels' => self::$labels
			]
		];
		$this->jiraClient->editIssue($jira->id(), $params);
	}
}
