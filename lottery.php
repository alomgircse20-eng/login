<?php
session_start();
include "connection.php";

$selectedNumber = null;
$winner = null;
$role = '';
$mcm_no = null;

if (isset($_SESSION['last_result'])) {
    $selectedNumber = $_SESSION['last_result']['selectedNumber'];
    $winner         = $_SESSION['last_result']['winner'];
    $role           = $_SESSION['last_result']['role'];
    $mcm_no         = $_SESSION['last_result']['mcm_no'];
    unset($_SESSION['last_result']);
}

if (!isset($_SESSION['step'])) $_SESSION['step'] = 1;

if (!isset($_SESSION['mcm_no'])) {
    $r = $conn->query("
        SELECT IFNULL(
            MAX(GREATEST(IFNULL(mcm_no,0), IFNULL(mcm_no_1,0))),632
        ) AS max_mcm 
        FROM name_iu
    ");
    $_SESSION['mcm_no'] = $r->fetch_assoc()['max_mcm'] + 1;
}
$mcm_no = $_SESSION['mcm_no'];

if (!isset($_SESSION['draw_done'])) $_SESSION['draw_done'] = false;

if (isset($_POST['draw']) || isset($_POST['redraw'])) {

    if (isset($_POST['redraw'])) {

        $role   = $_POST['role'];
        $mcm_no = $_POST['mcm_no'];
        $_SESSION['mcm_no'] = $mcm_no;
        $_SESSION['step']   = ($role === 'সভাপতি') ? 1 : 2;
        $chk = $conn->query("
            SELECT 1 FROM name_iu iu
            JOIN name_list nl ON iu.SL = nl.sl
            WHERE nl.status='active'
              AND ((iu.role='সভাপতি' AND iu.mcm_no='$mcm_no')
                OR (iu.role='পরিচালনাকারী' AND iu.mcm_no_1='$mcm_no'))
        ");

        if ($chk->num_rows == 0) {
            $_SESSION['error'] = "❌ Inactive সদস্যের জন্য পুনরায় লটারী করা যাবে না";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        if ($role === 'সভাপতি') {
            $conn->query("DELETE FROM name_iu WHERE mcm_no='$mcm_no' AND role='সভাপতি'");
        } else {
            $conn->query("DELETE FROM name_iu WHERE mcm_no_1='$mcm_no' AND role='পরিচালনাকারী'");
        }

    } else {
        if ($_SESSION['step'] == 1) {
            $r = $conn->query("
                SELECT IFNULL(
                    MAX(GREATEST(IFNULL(mcm_no,0), IFNULL(mcm_no_1,0))),632
                ) AS max_mcm 
                FROM name_iu
            ");
            $_SESSION['mcm_no'] = $r->fetch_assoc()['max_mcm'] + 1;
            $mcm_no = $_SESSION['mcm_no'];
            $_SESSION['draw_done'] = false;
        }
    }

    $role = ($_SESSION['step'] == 1) ? 'সভাপতি' : 'পরিচালনাকারী';
    $gradeCondition = ($_SESSION['step'] == 1)
        ? "nl.grade IN ('A','B')"
        : "nl.grade='B'";
    do {
        $selectedNumber = rand(1, 98);

        $used = $conn->query("
            SELECT 1 FROM name_iu
            WHERE SL='$selectedNumber'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ");

        $result = $conn->query("
            SELECT nl.sl, nl.Name, nl.Degination, nl.Department
            FROM name_list nl
            WHERE nl.sl='$selectedNumber'
              AND nl.status='active'
              AND $gradeCondition
        ");

    } while (($used && $used->num_rows > 0) || $result->num_rows == 0);

    $winner = $result->fetch_assoc();

    if ($role === 'সভাপতি') {
        $conn->query("
            INSERT INTO name_iu
            (mcm_no, SL, role, Name, Degination, Department)
            VALUES
            ('$mcm_no','{$winner['sl']}','$role',
             '{$winner['Name']}','{$winner['Degination']}','{$winner['Department']}')
        ");
    } else {
        $conn->query("
            INSERT INTO name_iu
            (mcm_no_1, SL, role, Name, Degination, Department)
            VALUES
            ('$mcm_no','{$winner['sl']}','$role',
             '{$winner['Name']}','{$winner['Degination']}','{$winner['Department']}')
        ");
    }

    if (!isset($_POST['redraw'])) {
        $_SESSION['step'] = ($_SESSION['step'] == 1) ? 2 : 1;
    }

    $_SESSION['draw_done'] = true;

    $_SESSION['last_result'] = [
        'selectedNumber'=>$selectedNumber,
        'winner'=>$winner,
        'role'=>$role,
        'mcm_no'=>$mcm_no
    ];

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>লটারি (President & Organizer)</title>
<style>
*{box-sizing:border-box;}
body{margin:0;font-family:'Segoe UI',sans-serif;background:linear-gradient(13005deg,#0f2027,#203a43,#2c5364);color:#fff;display:flex;flex-direction:column;min-height:100vh;}
.topnav{background:#198754;padding:5px 20px;display:flex;justify-content:center;gap:15px;}
.topnav a{color:#fff;text-decoration:none;font-weight:500;padding:8px 12px;border-radius:5px;}
.topnav a:hover{background:#157347;}
.topnav a.active{font-weight:bold;text-decoration:underline;}
.container{flex:1;text-align:center;margin:px auto;display:flex;flex-direction:column;align-items:center;}
h2{margin-bottom:10px;color:#ffeb3b;}
.numbers{background:#ffffff18;padding:px;border-radius:px;margin:px auto;display:flex;flex-wrap:wrap;justify-content:center;}
.num{display:inline-flex;justify-content:center;align-items:center;width:35px;height:35px;margin:4px;border-radius:50%;background:#ffffff25;font-size:16px;font-weight:bold;transition:all 0.2s ease;}
.selected{background:#00e676;color:#000;transform:scale(1.3);}
form{display:flex;justify-content:center;align-items:center;gap:10px;flex-wrap:wrap;margin-top:15px;}
button, select{padding:10px 20px;font-size:14px;border:none;border-radius:8px;cursor:pointer;}
button{background:#ffeb3b;color:#000;font-weight:bold;}
button:disabled{opacity:0.5;cursor:not-allowed;}
select{background:#fff;color:#000;}
.winner-box{margin:5px auto;background:#fff;color:#333;padding:20px;border-radius:12px;width:320px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.winner-box h3{margin:5px 0;color:#2e7d32;}
.footer{margin-top:15px;font-size:14px;opacity:.85;color:#fff;}
</style>
</head>
<body>

<div class="topnav">
     <a href="dashboard.php">Home</a>
    <a href="name_list.php">MCM Member List</a>
    <a href="it.php">Dashboard</a>
    <a href="lottery.php">Lottery</a>
    <a href="attandence.php">Attendance</a>
    <a href="register.php">Regulation</a>
    <a href="logout.php">Logout</a>
</div>

<div class="container">
    <h4>টিএমএসএস - MCM MEETING লটারী</h4>

    <div class="numbers">
        <?php for($i=1;$i<=98;$i++): 
            $cls = ($i === $selectedNumber) ? 'num selected' : 'num';
            echo "<div class='$cls'>$i</div>";
        endfor; ?>
    </div>

  <form method="post">
    <input type="hidden" name="mcm_no" value="<?= $mcm_no ?>">

      <button type="submit" name="draw">লটারী তুলুন</button>

    <select name="role" required>
        <option value="" disabled hidden>ভূমিকা</option>
        <option value="সভাপতি" <?= ($role=='সভাপতি')?'selected':'' ?>>সভাপতি</option>
        <option value="পরিচালনাকারী" <?= ($role=='পরিচালনাকারী')?'selected':'' ?>>পরিচালনাকারী</option>
    </select>
    
    <button type="submit" name="redraw" <?= ($_SESSION['draw_done'])?'':'disabled' ?>>
        🔁 পুনরায় লটারী
    </button>
</form>


    <?php if($selectedNumber): ?>
        <div class="winner-box">
            🎯 নাম্বার: <b><?= $selectedNumber ?></b><br>
            <h3><?= $role ?></h3>
            <?php if($winner): ?>
                MCM No: <b><?= $mcm_no ?></b><br>
                👤 Name: <?= htmlspecialchars($winner['Name']) ?><br>
                🧾 Degination: <?= htmlspecialchars($winner['Degination']) ?><br>
                🏢 ID: <?= htmlspecialchars($winner['Department']) ?><br>
            <?php else: ?>
                ❌ কোনো ডাটা পাওয়া যায়নি
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        Design & Developed by <b>Automation Cell (HGS)</b>
    </div>
</div>

</body>
</html>
