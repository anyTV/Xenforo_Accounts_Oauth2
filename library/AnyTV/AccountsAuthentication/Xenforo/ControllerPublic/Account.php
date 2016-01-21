<?php

class AnyTV_AccountsAuthentication_XenForo_ControllerPublic_Account extends XFCP_AnyTV_AccountsAuthentication_XenForo_ControllerPublic_Account
{
	public function actionExternalAccounts()
	{
		$response = parent::actionExternalAccounts();
		if ($response instanceof XenForo_ControllerResponse_View
			&& $response->subView instanceof XenForo_ControllerResponse_View
		)
		{
			$params =& $response->subView->params;

			if (! empty($params['external']['accountsfreedom']))
			{
				$external = $params['external']['accountsfreedom'];

				$accounts = new AnyTV_AccountsAuthentication_Accounts;
				$extraData = unserialize($external['extra_data']);

				$accountsUser = $accounts->getUserInfo($external['provider_key'], $extraData['token']);
				
				if (! empty($accountsUser))
				{
					$accountsUser = $accountsUser;
				}
				else
				{
					$accountsUser = false;
				}

				$params['accountsUser'] = $accountsUser;
			}
		}

		return $response;
	}


}