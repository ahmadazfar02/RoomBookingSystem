<?php
require_once __DIR__ . '/../includes/db_connect.php';

// Check for remember me cookie
if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $token);
    //added is_verified
    $sql = "SELECT id, username, Fullname, User_Type, is_verified FROM users WHERE remember_token = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $fullname, $User_Type, $is_verified);
            $stmt->fetch();

            // Allow login if verified OR if user is SuperAdmin (bypass check)
            $uType = trim($User_Type);
            $is_super = (strcasecmp($uType, 'SuperAdmin') == 0);

            //check verified aku tambah if(is verified)
            if ($is_verified==1 || $is_super){
            session_start();
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = $id;
            $_SESSION["username"] = $username;
            $_SESSION["Fullname"] = $fullname;
            $_SESSION["User_Type"] = $User_Type;
            
            $uType = trim($User_Type);
            if (strcasecmp($uType, 'Admin') == 0 || 
                strcasecmp($uType, 'Technical Admin') == 0 ||
                $is_super /*strcasecmp($uType, 'SuperAdmin') == 0*/) {
                
                header("location: ../admin/index-admin.php");
            } else {
                header("location: ../timetable.html");
            }
            exit;
            }
            
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $email = $_POST["username"];
    $password = $_POST["password"];

    $sql = "SELECT id, username, Fullname, password_hash, User_Type, is_verified FROM users WHERE username = ?";

    if ($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $email);

        if($stmt->execute()){
            $stmt->store_result();
            if($stmt->num_rows == 1){
                $stmt->bind_result($id, $username, $fullname, $hashed_password, $User_Type, $is_verified);
                $stmt->fetch();

                // Allow login if verified OR if user is SuperAdmin (bypass check)
                $uType = trim($User_Type);
                $is_super = (strcasecmp($uType, 'SuperAdmin') == 0);

                // Pass null string if password hash is null to prevent error
                if (password_verify($password, $hashed_password ?? '')){

                    //check verified aku tambah if(is verified)
                    if($is_verified == 0 && !$is_super){
                        header("Location: ../loginterface.html?error=" . urlencode("Please verify your email address before logging in. Check your inbox."));
                        exit;
                    }

                    session_start();
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["username"] = $username;
                    $_SESSION["Fullname"] = $fullname;
                    $_SESSION["User_Type"] = $User_Type;

                    if (isset($_POST['remember'])) {
                        $token = bin2hex(random_bytes(16));
                        $token_hash = hash('sha256', $token);

                        $update_sql = "UPDATE users SET remember_token = ? WHERE id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("si", $token_hash, $id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }

                        setcookie('remember_me', $token, time() + 60 * 60 * 24 * 30, '/'); // 30-day cookie
                    }

                    $uType = trim($User_Type);
                    if (strcasecmp($uType, 'Admin') == 0 || 
                        strcasecmp($uType, 'Technical Admin') == 0 ||
                        $is_super /*strcasecmp($uType, 'SuperAdmin') == 0*/) {
                        
                        header("location: ../admin/index-admin.php");
                    } else {
                        header("location: ../timetable.html");
                    }
                    exit;
                } 
                else {
                    // Redirect back with error message (Password incorrect)
                    header("Location: ../loginterface.html?error=" . urlencode("The password you entered is incorrect."));
                    exit;
                }
            }
            else {
                // Redirect back with error message (User not found)
                header("Location: ../loginterface.html?error=" . urlencode("No account found with that username."));
                exit;
            }
        }
        $stmt->close();
    }
}
$conn->close();
?>