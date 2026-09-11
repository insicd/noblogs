<?php

declare(strict_types=1);

/**
 * Sign-in, sign-up and password recovery strings.
 *
 * Error messages are deliberately vague: none of them may reveal whether an
 * email address has an account on Noblogs.
 */
return [
    // -----------------------------------------------------------------------
    // Sign in
    // -----------------------------------------------------------------------
    'auth.login.title'        => 'Sign in',
    'auth.login.heading'      => 'Sign in to your account',
    'auth.login.intro'        => 'Use the credentials you signed up with.',
    'auth.login.email'        => 'Email address',
    'auth.login.password'     => 'Password',
    'auth.login.submit'       => 'Sign in',
    'auth.login.forgot'       => 'Forgot your password?',
    'auth.login.no_account'   => 'No blog yet?',
    'auth.login.register'     => 'Start one',
    'auth.login.failed'       => 'Wrong email or password.',
    'auth.login.welcome'      => 'Welcome back.',
    'auth.login.next_notice'  => 'Sign in to continue.',
    'auth.logout.done'        => 'You have signed out.',
    'auth.logout.submit'      => 'Sign out',

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------
    'auth.register.title'          => 'Sign up',
    'auth.register.heading'        => 'Start your blog',
    'auth.register.intro'          => 'One form: the account and the blog are created together. No paid plans, no credit card.',
    'auth.register.email'          => 'Email address',
    'auth.register.email_hint'     => 'Used to verify the account and to recover your password. We never show it to anyone.',
    'auth.register.password'       => 'Password',
    'auth.register.password_hint'  => 'At least :min characters. A long sentence beats a complicated word.',
    'auth.register.subdomain'      => 'Blog address',
    'auth.register.subdomain_hint' => 'Lowercase letters, digits and hyphens. This is where your blog will live.',
    'auth.register.blog_title'     => 'Blog title',
    'auth.register.blog_title_hint' => 'You can change it whenever you like.',
    'auth.register.submit'         => 'Create the blog',
    'auth.register.have_account'   => 'Already have an account?',
    'auth.register.login'          => 'Sign in',
    'auth.register.terms_note'     => 'By signing up you accept the terms of use and the privacy notice.',
    'auth.register.created'        => 'Blog created. Check your inbox to confirm your email address.',
    'auth.register.created_ready'  => 'Blog created. You can sign in now.',
    'auth.register.review_notice'  => 'Brand new blogs are not indexed by search engines until a moderator approves them. The blog is online and readable straight away.',
    'auth.register.review_path_notice' => 'Until the blog is approved it is reachable only at :domain/chosen-name. The third-level host is switched on if and when the administrators enable it.',
    'auth.register.exists_subject' => 'Someone tried to sign up with your address',
    'auth.register.exists_body'    => "Hello,\n\nsomeone tried to open a new account on :site using this email address, which already has one.\n\nIf that was you, just sign in:\n:login\n\nIf you forgot your password, you can reset it here:\n:reset\n\nIf it was not you, there is nothing to do: no account was created and nothing was disclosed to whoever filled in the form.\n",

    // -----------------------------------------------------------------------
    // Email verification
    // -----------------------------------------------------------------------
    'auth.verify.title'         => 'Verify your email address',
    'auth.verify.heading'       => 'Check your inbox',
    'auth.verify.body'          => 'We sent you a message with a confirmation link. Open it to fully activate the account. If nothing arrives within a few minutes, check your spam folder.',
    'auth.verify.sent_to'       => 'Message sent to :email.',
    'auth.verify.resend'        => 'Send the message again',
    'auth.verify.resent'        => 'If the address is waiting for verification, a new message is on its way.',
    'auth.verify.done'          => 'Email address confirmed. You can sign in now.',
    'auth.verify.invalid_title' => 'Invalid link',
    'auth.verify.invalid_body'  => 'This confirmation link is no longer valid: it may already have been used, or replaced by a newer one. Ask for another from the verification page.',
    'auth.verify.email_subject' => 'Confirm your address on :site',
    'auth.verify.email_body'    => "Hello,\n\nyour blog on :site is ready: :blog\n\nTo finish signing up, confirm this email address:\n:url\n\nIf you did not ask for this account, ignore the message: without confirmation the account stays unusable.\n",

    // -----------------------------------------------------------------------
    // Forgotten password and reset
    // -----------------------------------------------------------------------
    'auth.password.request_title'   => 'Forgotten password',
    'auth.password.request_heading' => 'Reset your password',
    'auth.password.request_intro'   => 'Enter your account email address and we will send you a link to pick a new password.',
    'auth.password.email'           => 'Email address',
    'auth.password.request_submit'  => 'Send the link',
    'auth.password.sent'            => 'If the address matches an account, the reset link is on its way. It is valid for two hours.',
    'auth.password.reset_title'     => 'New password',
    'auth.password.reset_heading'   => 'Pick a new password',
    'auth.password.reset_intro'     => 'The link works once: after you save it will stop working.',
    'auth.password.new'             => 'New password',
    'auth.password.reset_submit'    => 'Save the password',
    'auth.password.reset_done'      => 'Password updated. You can sign in now.',
    'auth.password.invalid_title'   => 'Expired link',
    'auth.password.invalid_body'    => 'This link is no longer valid: it lasts two hours and works once. You can ask for another one.',
    'auth.password.request_again'   => 'Ask for a new link',
    'auth.password.email_subject'   => 'Reset your password on :site',
    'auth.password.email_body'      => "Hello,\n\nsomeone asked to reset the password of the :site account tied to this address.\n\nIf that was you, pick a new password here:\n:url\n\nThe link lasts two hours and works once.\n\nIf it was not you, ignore this message: your current password still works and nobody got into the account.\n",
    'auth.password.back_to_login'   => 'Back to sign in',

    // -----------------------------------------------------------------------
    // Validation errors
    // -----------------------------------------------------------------------
    'auth.error.email_required'    => 'Enter your email address.',
    'auth.error.email_invalid'     => 'This email address does not look valid.',
    'auth.error.password_required' => 'Enter your password.',
    'auth.error.password_short'    => 'The password must be at least :min characters long.',
    'auth.error.password_long'     => 'The password is too long: :max characters at most.',
    'auth.error.password_weak'     => 'This password is too easy to guess. Try another one, perhaps a sentence of several words.',
    'auth.error.title_required'    => 'Give the blog a title.',
    'auth.error.title_long'        => 'The blog title can be at most :max characters long.',
    'auth.error.generic'           => 'The operation could not be completed. Please try again.',
];
