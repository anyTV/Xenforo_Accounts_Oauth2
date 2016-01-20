<?php

class AnyTV_AccountsAuthentication_XenForo_ControllerPublic_Register extends XFCP_AnyTV_AccountsAuthentication_XenForo_ControllerPublic_Register
{
	public function actionAccountsfreedom()
	{
		$accounts = new AnyTV_AccountsAuthentication_Accounts;
		if (! $accounts->isConnectable())
		{
			return $this->responseError(new XenForo_Phrase('something_went_wrong_please_try_again'));
		}

		$assocUserId = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
		$redirect = $this->_getExternalAuthRedirect();

		$session = XenForo_Application::getSession();

		$redirectUri = XenForo_Link::buildPublicLink('canonical:register/accountsfreedom', false, array(
			'assoc' => ($assocUserId ? $assocUserId : false)
		));

		if ($this->_input->filterSingle('reg', XenForo_Input::UINT))
		{
			$session->set('loginRedirect', $redirect);
			$session->remove('accountsToken');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				$accounts->getAccountsRequestUrl($redirectUri)
			);
		}

		$accountsToken = $this->_input->filterSingle('token', XenForo_Input::STRING);
		if (! $accountsToken)
		{
			$accountsToken = $session->get('accountsToken');
		}

		$accountsUser = false;
		if (! $accountsToken)
		{
			$error = $this->_input->filterSingle('error', XenForo_Input::STRING);
			if ($error == 'access_denied')
			{
				return $this->responseError(new XenForo_Phrase('you_did_not_grant_permission_to_access_external_account'));
			}

			$code = $this->_input->filterSingle('code', XenForo_Input::STRING);
			if (!$code)
			{
				return $this->responseError(new XenForo_Phrase('accountsfreedom_error_occurred_while_connecting_with_accountsfreedom1'));
			}

			$state = $this->_input->filterSingle('state', XenForo_Input::STRING);
			if (!$state || !$session->get('accountsCsrfState') || $state !== $session->get('accountsCsrfState'))
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('canonical:index')
				);
			}

			$token = $accounts->getAccessTokenFromCode($code, $redirectUri);

			if (! isset($token['access_token']))
			{
				return $this->responseError(new XenForo_Phrase('accountsfreedom_error_occurred_while_connecting_with_accountsfreedom2'));
			}

			$accountsToken = $token['access_token'];

			$accountsUser = $accounts->getUserInfo(null, $accountsToken);
		}

		if (! isset($accountsUser['user_id']))
		{
			return $this->responseError(new XenForo_Phrase('accountsfreedom_error_occurred_while_connecting_with_accountsfreedom3'));
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$accountsAssoc = $userExternalModel->getExternalAuthAssociation('accountsfreedom', $accountsUser['user_id']);
		if ($accountsAssoc && $userModel->getUserById($accountsAssoc['user_id']))
		{
			$userExternalModel->updateExternalAuthAssociationExtra(
				$accountsAssoc['user_id'], 'accountsfreedom', array('token' => $accountsToken)
			);

			$redirect = XenForo_Application::getSession()->get('loginRedirect');
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			$visitor = XenForo_Visitor::setup($accountsAssoc['user_id']);
			XenForo_Application::getSession()->userLogin($accountsAssoc['user_id'], $visitor['password_date']);

			$this->_getUserModel()->setUserRememberCookie($accountsAssoc['user_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		$existingUser = false;
		$emailMatch = false;
		if (XenForo_Visitor::getUserId())
		{
			$existingUser = XenForo_Visitor::getInstance();
		}
		else if ($assocUserId)
		{
			$existingUser = $userModel->getUserById($assocUserId);
		}

		$viewName = 'AnyTV_AccountsAuthentication_ViewPublic_Accounts_Register';
		$templateName = 'register_accountsfreedom';

		XenForo_Application::getSession()->set('accountsToken', $accountsToken);
		XenForo_Application::getSession()->set('accountsUser', $accountsUser);

		if ($existingUser)
		{
			// must associate: matching user
			return $this->_getExternalRegisterFormResponse($viewName, $templateName, array(
				'associateOnly' => true,

				'accountsfreedom' => $accountsUser,

				'existingUser' => $existingUser,
				'emailMatch' => $emailMatch,
				'redirect' => $redirect
			));
		}

		$this->_assertRegistrationActive();

		if (!empty($accountsUser['birthday']))
		{
			$this->_validateBirthdayString($accountsUser['birthday'], 'm/d/y');
		}

		return $this->_getExternalRegisterFormResponse($viewName, $templateName, array(
			'accountsfreedom' => $accountsUser,
			'redirect' => $redirect,
			'showDob' => empty($accountsUser['birthday'])
		));
	}

	public function actionAccountsfreedomRegister()
	{
		$this->_assertPostOnly();
		$session = XenForo_Application::getSession();

		$accountsToken = $session->get('accountsToken');
		$accountsUser = $session->get('accountsUser');

		if (empty($accountsUser['user_id']))
		{
			return $this->responseError(new XenForo_Phrase('accountsfreedom_error_occurred_while_connecting_with_accountsfreedom4'));
		}

		$userExternalModel = $this->_getUserExternalModel();

		$redirect = XenForo_Application::getSession()->get('loginRedirect');
		if (!$redirect)
		{
			$redirect = $this->getDynamicRedirect(false, false);
		}

		$doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING)
			|| $this->_input->filterSingle('force_assoc', XenForo_Input::UINT)
		);

		if ($doAssoc)
		{
			$userId = $this->_associateExternalAccount();

			$userExternalModel->updateExternalAuthAssociation(
				'accountsfreedom', $accountsUser['user_id'], $userId, array('token' => $accountsToken)
			);

			$session->remove('loginRedirect');
			$session->remove('accountsToken');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		$data = $this->_input->filter(array(
			'username'   => XenForo_Input::STRING,
			'timezone'   => XenForo_Input::STRING,
			'location'   => XenForo_Input::STRING,
			'dob_day'    => XenForo_Input::UINT,
			'dob_month'  => XenForo_Input::UINT,
			'dob_year'   => XenForo_Input::UINT,
			'email'		 => XenForo_Input::STRING
		));

		if (isset($accountsUser['gender']))
		{
			switch ($accountsUser['gender'])
			{
				case 'man':
				case 'male':
					$data['gender'] = 'male';
					break;

				case 'woman':
				case 'female':
					$data['gender'] = 'female';
					break;
			}
		}

		if (!empty($accountsUser['birthday']))
		{
			$birthday = $this->_validateBirthdayString($accountsUser['birthday'], 'm/d/y');
			if ($birthday)
			{
				$data['dob_year'] = $birthday[0];
				$data['dob_month'] = $birthday[1];
				$data['dob_day'] = $birthday[2];
			}
		}

		if (! empty($accountsUser['website']))
		{
			list($website) = preg_split('/\r?\n/', $accountsUser['website']);
			if ($website && Zend_Uri::check($website))
			{
				$data['homepage'] = $website;
			}
		}

		if (!empty($accountsUser['location']['name']))
		{
			$data['location'] = $accountsUser['location']['name'];
		}

		$writer = $this->_setupExternalUser($data);
		if (!$this->_validateBirthdayInput($writer, $birthdayError))
		{
			$writer->error($birthdayError);
		}

		$spamModel = $this->_runSpamCheck($writer);

		$writer->advanceRegistrationUserState(false);
		$writer->save();
		$user = $writer->getMergedData();

		$spamModel->logSpamTrigger('user', $user['user_id']);

		try
		{
			$avatarData = file_get_contents($accountsUser['profile_picture']);
		}
		catch(Exception $e) {
			$avatarData = '';
		}

		if ($avatarData)
		{
			$this->_applyAvatar($user, $avatarData);
		}

		$userExternalModel->updateExternalAuthAssociation(
			'accountsfreedom', $accountsUser['user_id'], $user['user_id'], array('token' => $accountsToken)
		);

		$session->remove('loginRedirect');
		$session->remove('accountsToken');
		$session->remove('accountsUser');

		return $this->_completeRegistration($user, array('redirect' => $redirect));
	}


}