<?php

session_start();
require_once 'config/connect.php';

if (isset($_SESSION['alert_message'])) {
    $alert_type = $_SESSION['alert_type']; 
    $alert_message = $_SESSION['alert_message'];
    unset($_SESSION['alert_message'], $_SESSION['alert_type']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

   // Register Process
   if(isset($_POST['register'])){

      $studName = filter_var($_POST['studName'], FILTER_SANITIZE_STRING);
      $studEmail = filter_var($_POST['studEmail'], FILTER_SANITIZE_EMAIL);
      $studNoID = filter_var($_POST['studNoID'], FILTER_SANITIZE_STRING);
      $studProgramme = filter_var($_POST['studProgramme'], FILTER_SANITIZE_STRING);
      $studSem = filter_var($_POST['studSem'], FILTER_SANITIZE_NUMBER_INT);
      $studPass = filter_var($_POST['studPass'], FILTER_SANITIZE_STRING);

      // check if student already exists (by Email)
      $checkEmail = $conn->prepare("SELECT * FROM student WHERE studEmail = ?");
      $checkEmail->execute([$studEmail]);

      if ($checkEmail->rowCount() > 0) {
         $_SESSION['alert_message'] = "⚠️ Email Address Already Exists!";
         $_SESSION['alert_type'] = "error";
         header("Location: ".$_SERVER['PHP_SELF']);
         exit;
      } else {
         // ✅ Hash password securely
         $hashedPassword = password_hash($studPass, PASSWORD_DEFAULT);

         $insert_stud = $conn->prepare("INSERT INTO `student`(studName, studEmail, studPass, studNoID, studProgramme, studSem) VALUES (?,?,?,?,?,?)");
         $insert_stud->execute([$studName, $studEmail, $hashedPassword, $studNoID, $studProgramme, $studSem]);

         $_SESSION['alert_message'] = "🎉 Registration Successful! You can now log in.";
         $_SESSION['alert_type'] = "success";
         header("Location: ".$_SERVER['PHP_SELF']);
         exit;
      }
   }

   // Login Process
   if(isset($_POST['login'])){

      $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
      $pass = filter_var($_POST['pass'], FILTER_SANITIZE_STRING);

      // Lecturer
      $select_lect = $conn->prepare("SELECT * FROM `lecturer` WHERE lectEmail = ?");
      $select_lect->execute([$email]);
      $lect = $select_lect->fetch(PDO::FETCH_ASSOC);

      if($lect && password_verify($pass, $lect['lectPass'])){
         $_SESSION['lect_id'] = $lect['lectID'];
         $_SESSION['lectName'] = $lect['lectName'];
         $_SESSION['alert_message'] = "✅ Welcome back, ".$lect['lectName']."!";
         $_SESSION['alert_type'] = "success";
         header('location:lecturer/dashboard.php');
         exit;
      }

      // Student
      $select_stud = $conn->prepare("SELECT * FROM `student` WHERE studEmail = ?");
      $select_stud->execute([$email]);
      $stud = $select_stud->fetch(PDO::FETCH_ASSOC);

      if($stud && password_verify($pass, $stud['studPass'])){
         $_SESSION['stud_id'] = $stud['studID'];
         $_SESSION['studName'] = $stud['studName'];
         $_SESSION['alert_message'] = "✅ Welcome back, ".$stud['studName']."!";
         $_SESSION['alert_type'] = "success";
         header('location: student/dashboard.php');
         exit;
      }

      // ❌ Error Alert Message
      $_SESSION['alert_message'] = "❌ Incorrect Email or Password!";
      $_SESSION['alert_type'] = "error";
      header("Location: ".$_SERVER['PHP_SELF']);
      exit;
   }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>UPTM CMS</title>
   <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
   <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png?v=<?php echo filemtime('assets/favicon-32x32.png'); ?>">
   <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png?v=<?php echo filemtime('assets/favicon-16x16.png'); ?>">
   <style>
      @import url('https://fonts.googleapis.com/css2?family=Popppins:wght@300;400;500;600;700;800;900;&display=swap');
     
      * {
         margin : 0;
         padding: 0;
         box-sizing: border-box;
         font-family: 'Poppins' , sans-serif;
      }

      body {
         display: flex;
         justify-content: center;
         align-items: center;
         min-height: 100vh;
         background: linear-gradient(90deg, #e2e2e2, #c9d6ff);
      }

      .container {
         position: relative;
         width: 850px;
         height: 580px;
         background: #fff;
         border-radius: 30px;
         box-shadow: 0 0 30px rgba(0, 0, 0, .2);
         margin: 20px;
         overflow: hidden;
      }

      .form-box {
         position: absolute;
         right: 0;
         width: 50%;
         height: 100%;
         background: #fff;
         display: flex;
         align-items: center;
         color: #333;
         text-align: center;
         padding: 40px;
         z-index: 1;
         transition: .6s ease-in-out 1.2s, visibility 0s 1s;
      }

      .container.active .form-box {
         right: 50%;
      }

      .form-box.register {
         visibility: hidden;
      }

      .container.active .form-box.register {
         visibility: visible;
      }

      form {
         width: 100%;
      }

      .container h1{
         font-size: 36px;
         margin: -10px 0;
      }

      .input-box {
         position: relative;
         margin: 30px 0;
      }

      .input-box input {
         width: 100%;
         padding: 13px;
         background: #eee;
         border-radius: 8px;
         border: none;
         outline: none;
         font-size: 16px;
         color: #333;
         font-weight: 500;
      }

      .input-box input::placeholder {
         color: #888;
         font-weight: 400;
      }

      .input-box i {
         position: absolute;
         right: 20px;
         top: 50%;
         transform: translateY(-50%);
         font-size: 20px;
         color: #888;
      }

      .btn {
         width: 100%;
         height: 48px;
         background: #7494ec;
         border-radius: 8px;
         box-shadow: 0 0 10px rgba(0, 0, 0, .1);
         border: none;
         cursor: pointer;
         color: #fff;
         font-weight: 600;
      }

      .container p {
         font-size: 14.5px;
         margin: 15px 0;
      }

      .toggle-box {
         position: absolute;
         width: 100%;
         height: 100%;
      }

      .toggle-box::before {
         content: '';
         position: absolute;
         left: -250%;
         width: 300%;
         height: 100%;
         background: #7494ec;
         border-radius: 150px;
         z-index: 2;
         transition: 1.8s ease-in-out;
      }

      .container.active .toggle-box::before {
         left: 50%;
      }

      .toggle-panel {
         position: absolute;
         width: 50%;
         height: 100%;
         color: #fff;
         display: flex;
         flex-direction: column;
         justify-content: center;
         align-items: center;
         z-index: 2;
         transition: .6s ease-in-out;
      }

      .toggle-panel.toggle-left {
         left: 0;
         transition-delay: 1.2s;
      }

      .container.active .toggle-panel.toggle-right {
         right: 0;
         transition-delay: 1.2s;
      }

      .container.active .toggle-panel.toggle-left {
         left: -50%;
         transition-delay: .6s;
      }

      .toggle-panel.toggle-right {
         right: -50%;
         transition-delay: .6s;
      }

      .toggle-panel p {
         margin-bottom: 20px;
      }

      .toggle-panel .btn{
         width: 160px;
         height: 46px;
         background: transparent;
         border: 2px solid #fff;
         box-shadow: none;
      }

   .alert {
      position: fixed;             
      top: 20px;                   
      left: 50%;                   
      transform: translateX(-50%); 
      z-index: 9999;               
      padding: 15px 25px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 15px;
      text-align: center;
      min-width: 300px;
      max-width: 90%;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
      animation: alertFadeInOut 5s ease forwards;
   }

   .alert.success {
      background-color: #e7f9ed;
      color: #218838;
      border: 1px solid #b1e0c2;
      box-shadow: 0 0 8px rgba(33,136,56,0.2);
   }

   .alert.error {
      background-color: #ffe7e7;
      color: #cc0000;
      border: 1px solid #f5b5b5;
      box-shadow: 0 0 8px rgba(204,0,0,0.2);
   }

@keyframes fadeInOut {
   0% {opacity: 0; transform: translateY(-10px);}
   10%, 90% {opacity: 1; transform: translateY(0);}
   100% {opacity: 0; transform: translateY(-10px);}
}

      @media screen and (max-width: 650px) {
         .container {
            height: calc(102vh - 40px);
         }

         .form-box {
            bottom: 0;
            width : 100%;
            height: 70%;
         }

         .container.active .form-box {
            right: 0;
            bottom: 30%;
         }

         .toggle-box::before {
            left: 0;
            top: -270%;
            width: 100%;
            height: 300%;
            border-radius: 20vw;
         }

         .container.active .toggle-box::before {
            left: 0;
            top: 70%;
         }

         .toggle-panel {
            width: 100%;
            height: 30%;
         }

         .toggle-panel.toggle-left {
            top: 0;
         }

         .container.active .toggle-panel.toggle-left {
            left: 0;
            top: -30%;
         }

         .toggle-panel.toggle-right {
            right: 0;
            bottom: -30%;
         }

         .container.active .toggle-panel.toggle-right {
            bottom: 0;
         }
      }

      @media screen and (max-width: 400px) {
         .form-box {
            padding: 20px;
         }

         .toggle-panel h1 {
            font-size: 30px;
         }
      }

@keyframes alertFadeInOut {
   0% { opacity: 0; transform: translate(-50%, -20px); }
   10%, 90% { opacity: 1; transform: translate(-50%, 0); }
   100% { opacity: 0; transform: translate(-50%, -20px); }
}

      @media screen and (max-width: 650px) {
          .alert {
            top: 15px;              
            width: 90%;             
            min-width: unset;       
            font-size: 14px;        
            padding: 12px 18px;     
         }
      }

      @media screen and (max-width: 400px) {
         .alert {
            width: 95%;             
            font-size: 13px;       
            border-radius: 8px;
            padding: 10px 16px;
         }
      }

   </style>
</head>
<body>

   <div class="container">

   <?php if (isset($alert_message)): ?>
   <div class="alert <?= $alert_type ?>">
      <?= htmlspecialchars($alert_message) ?>
   </div>
<?php endif; ?>


      <div class="form-box login">
         <form action="" method="POST">
            <h1>Login</h1>
            <div class="input-box">
               <input type="email" name="email" placeholder="Email" required>
               <i class='bx bxs-envelope'></i>
            </div>
            <div class="input-box">
               <input type="password" name="pass" placeholder="Password" required>
               <i class='bx bxs-lock-alt'></i>
            </div>
            <button type="submit" name="login" class="btn">Login</button>
         </form>
      </div>

      <div class="form-box register">
         <form action="" method="POST">
            <h1>Registration</h1>
            <div class="input-box">
               <input type="text" name="studName" placeholder="Name" required>
               <i class='bx bxs-user'></i>
            </div>
            <div class="input-box">
               <input type="text" name="studNoID" placeholder="ID Number" required>
               <i class='bx bxs-id-card'></i>
            </div>
            <div class="input-box">
               <input type="text" name="studProgramme" placeholder="Programme" required>
               <i class='bx bxs-book-bookmark'></i>
            </div>
            <div class="input-box">
               <input type="text" name="studSem" placeholder="Current Semester" required>
               <i class='bx bxs-calendar'></i>
            </div>
            <div class="input-box">
               <input type="email" name="studEmail" placeholder="Email" required>
               <i class='bx bxs-envelope'></i>
            </div>
            <div class="input-box">
               <input type="password" name="studPass" placeholder="Password" required>
               <i class='bx bxs-lock-alt'></i>
            </div>
            <button type="submit" name="register" class="btn">Register</button>
         </form>
      </div>

      <div class="toggle-box">
         <div class="toggle-panel toggle-left">
            <h1>Hello, Welcome!</h1>
            <p>Don't have an account?</p>
            <button class="btn register-btn">Register</button>
         </div>
         <div class="toggle-panel toggle-right">
            <h1>Welcome Back!</h1>
            <p>Already have an account?</p>
            <button class="btn login-btn">Login</button>
         </div>
      </div>

   </div>
      
   <script>
      const container = document.querySelector('.container');
      const registerBtn = document.querySelector('.register-btn');
      const loginBtn = document.querySelector('.login-btn');

      registerBtn.addEventListener('click', () => {
         container.classList.add('active');
      });

      loginBtn.addEventListener('click', () => {
         container.classList.remove('active');
      });

      setTimeout(() => {
         const alert = document.querySelector('.alert');
         if(alert) alert.remove();
      }, 8000); 

   </script>
</body>
</html>
