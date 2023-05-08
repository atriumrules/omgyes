<?php

// Extends to class Users
// Only Public methods

require_once SITE_ROOT . "/app/models/UsersModel.php";
require_once "SessionController.php";
date_default_timezone_set('Europe/Vienna');
class UserController extends Users
{
    public function createUserSession($user)
    {
        //Session::init();
        Session::set("login", true);
        Session::set("uid", (int) $user->uid);
        Session::set("username", $user->username);
        Session::set("admin", (int) $user->admin);
        Session::set("supp", (int) $user->supp);
        Session::set("banned", (int) $user->banned);
        Session::set("invitedBy", $user->invitedBy);
        Session::set("createdAt", $user->createdAt);
    }

    public function gettokenarray()
    {
        $username = Session::get("username");
        return $this->tokenarray($username);
    }

    public function logoutUser($log = true)
    {
        $username = Session::get("username");
        if ($log) {
            $this->log($username, "Logged out", auth_logs);
        }

        setcookie("login_cookie", "", time() - 3600, '/');
        session_unset();
        $_SESSION = [];
        $_SESSION = array();
        session_destroy();
        Util::redirect("/auth/login.php");
    }


    public function banreason($username)
    {
        return $this->getbanreason($username);
    }

    public function getusernews()
    {
        return $this->getnews();
    }

    public function getlogarray($username)
    {
        $result = $this->logarray($username);
        return $result;
    }

    public function flush()
    {
        $result = $this->flushlogs();
        return $result;
    }

    public function getresetcount($uid)
    {
        return $this->gethwidcount($uid);
    }
    public function getresetdate($uid)
    {
        return $this->getlastreset($uid);
    }



    public function registerUser($data)
    {
        // Bind login data
        $username = trim($data["username"]);
        $password = $data["password"];
        $confirmPassword = $data["confirmPassword"];
        $invCode = trim($data["invCode"]);

        // Empty error vars
        $userError = $passError = "";
        $usernameValidation = '/^[a-zA-Z0-9]*$/';

        // Validate username on length and letters/numbers
        if (empty($username)) {
            return $userError = "Please enter a username.";
        } elseif (strlen($username) < 3) {
            return $userError = "Username is too short.";
        } elseif (strlen($username) > 14) {
            return $userError = "Username is too long.";
        } elseif (!preg_match($usernameValidation, $username)) {
            return $userError = "Username must only contain alphanumericals!";
        } else {
            // Check if username exists
            $userExists = $this->usernameCheck($username);
            if ($userExists) {
                return $userError = "Username already exists, try another.";
            }
        }

        // Validate password on length
        if (empty($password)) {
            return $passError = "Please enter a password.";
        } elseif (strlen($password) < 4) {
            return $passError = "Password is too short.";
        }

        // Validate confirmPassword on length
        if (empty($confirmPassword)) {
            return $passError = "Please enter a password.";
        } elseif ($password != $confirmPassword) {
            return $passError = "Passwords do not match, please try again.";
        }

        if ($this->SystemData()->invites == true) {
            // Validate invCode
            if (empty($invCode)) {
                return $invCodeError = "Please enter an invite code.";
            } else {
                // Check if invite code is valid
                $invCodeExists = $this->invCodeCheck($invCode);

                if (!$invCodeExists) {
                    return $invCodeError = "Invite code is invalid or already used.";
                }
            }
        }

        // Check if all errors are empty
        if (
            empty($userError) &&
            empty($passError) &&
            empty($invCodeError) &&
            empty($userExistsError) &&
            empty($invCodeError)
        ) {
            // Hashing the password
            $hashedPassword = password_hash($password, PASSWORD_ARGON2I);

            $result = $this->register($username, $hashedPassword, $invCode);


            // Session start
            if ($result) {
                $this->log($username, "Just registered", auth_logs);
                Util::redirect("/auth/login.php");
            } else {
                return "Something went wrong.";
            }
        }
    }

    public function loginUser($data)
    {
        // Bind login data
        $username = trim($data["username"]);
        $password = $data["password"];

        // Empty error vars
        $userError = $passError = "";

        // Validate username
        if (empty($username)) {
            return $userError = "Please enter a username.";
        }

        // Validate password
        if (empty($password)) {
            return $passError = "Please enter a password.";
        }

        // Check if all errors are empty
        if (empty($userError) && empty($passError)) {
            $result = $this->login($username, $password);

            if ($result) {
                // Session start
                $this->createUserSession($result);

                if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                    $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                    $_SERVER["HTTP_CLIENT_IP"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                }
                $client = @$_SERVER["HTTP_CLIENT_IP"];
                $forward = @$_SERVER["HTTP_X_FORWARDED_FOR"];
                $remote = $_SERVER["REMOTE_ADDR"];

                if (filter_var($client, FILTER_VALIDATE_IP)) {
                    $ip = $client;
                } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
                    $ip = $forward;
                } else {
                    $ip = $remote;
                }

                $this->logIP($ip, $username);

                $token = bin2hex(random_bytes(16));

                $this->addrememberToken($token, $username);

                setcookie("login_cookie", $token, time() + 31556926, '/');
                $_SESSION["username"] = $username;
                $this->log($username, "Logged in", auth_logs);
                $this->loglogin();
                Util::redirect("/index.php");
            } else {
                return "Username/Password is wrong.";
            }
        }
    }

    public function deletetoken($token)
    {
        return $this->tokendelete($token);
    }

    public function deleteother($token)
    {
        return $this->delother($token);
    }

    
    public function tokenlogin($token)
    {
        $result = $this->logintoken($token);

        if ($result) {
            // Session start
            $this->createUserSession($result);
            $username = Session::get("username");
            $this->log($username, "Logged in via cookie", auth_logs);
            $this->loglogin();
            Util::redirect("/index.php");
        }
        else
        {
            $this->logoutUser(false);
            return "Login with stored token failed.";
        }
    }

    public function activateSub($code)
    {
        // Bind data
        $username = Session::get("username");
        $subCode = $code;

        if (empty($subCode)) {
            return "Please enter a code.";
        } else {
            $subCodeExists = $this->subCodeCheck($subCode);

            if ($subCodeExists) {
                $this->log($username, "Activated a sub", user_logs);
                return $this->subscription($subCode, $username);
            } else {
                return "Subscription code is invalid.";
            }
        }
    }

    public function updateUserPass($data)
    {
        // Bind data
        $username = Session::get("username");
        $currentPassword = $data["currentPassword"];
        $newPassword = $data["newPassword"];
        $confirmPassword = $data["confirmPassword"];
    
        // Empty error array
        $errors = array();
    
        // Validate password
        if (empty($currentPassword)) {
            $errors[] = "Please enter a current password.";
        }
    
        if (empty($newPassword)) {
            $errors[] = "Please enter a new password.";
        } elseif (strlen($newPassword) < 4) {
            $errors[] = "New password is too short.";
        }
    
        if (empty($confirmPassword)) {
            $errors[] = "Please enter a confirm password.";
        } elseif ($confirmPassword != $newPassword) {
            $errors[] = "Confirm password does not match new password, please try again.";
        }
    
        // Check if there are any errors
        if (empty($errors)) {
            // Hashing the password
            $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2I);
            $result = $this->updatePass($currentPassword, $hashedPassword, $username);
    
            if ($result) {
                $this->flushtokens($username);
                Util::redirect("/auth/logout.php");
            } else {
                $errors[] = "Your current password does not match.";
            }
        }
        return $errors;
    }

    public function getUserCount()
    {
        return $this->userCount();
    }

    public function getBannedUserCount()
    {
        return $this->bannedUserCount();
    }

    public function getActiveUserCount()
    {
        return $this->activeUserCount();
    }

    public function getNewUser()
    {
        return $this->newUser();
    }

    public function getSubStatus($username = null)
    {
        if ($username === null) {
            // Bind data
            $username = Session::get("username");
        }
        return $this->subActiveCheck($username);
    }

    public function getavatarname($username)
    {
        return $this->avatarname($username);
    }

    public function getuserbyuid($uid)
    {
        return $this->getbyuid($uid);
    }

    public function gettime()
    {
        return $this->timesincefrozen();
    }

    public function log($username, $action, $webhook)
    {
        return $this->sendlog($username, $action, $webhook);
    }

    public function getlastlogin()
    {
        return $this->lastlogin(Session::Get("username"));
    }

    public function getlastip()
    {
        $username = Session::Get("username");
        return $this->lastip($username);
    }

    public function getfrozen()
    {
        $username = Session::Get("username");
        return $this->isfrozen($username);
    }

    public function sendmsg($msg)
    {
        $username = Session::get("username");
        return $this->msgsend($username, $msg);
    }
    public function getmsgs()
    {
        return $this->getshoutbox();
    }
    
    public function getuser($identifier)
    {
        return $this->getuserdata($identifier);
    }
}
