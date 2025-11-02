ALTER TABLE `users`
ADD `remember_token` VARCHAR(255) NULL DEFAULT NULL AFTER `User_Type`,
ADD `remember_token_expiry` DATETIME NULL DEFAULT NULL AFTER `remember_token`;
