<?php

class AnyTV_AccountsAuthentication_Listener
{
	public static function loadProxy($class, array &$extend)
	{
		static $proxy = array(
			'XenForo_ControllerPublic_Register',
			'XenForo_ControllerPublic_Account'
		);
		if (in_array($class, $proxy))
		{
			$extend[] = 'AnyTV_AccountsAuthentication_' . $class;
		}
	}



}