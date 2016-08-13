<?php
/**
 * Created by PhpStorm.
 * User: user1
 * Date: 03.08.16
 * Time: 15:09
 */

require_once ('../include/functions.php');

$path = '../../db/';
$file = get_database_file();
$fullname = realpath($path . $file);
$data = get_database_data($fullname);
?>

<?php foreach ($data as $table => $rows):?>
    <h3><?php echo $table;?></h3>
    <table class="table">
        <tr>
        <?php if(isset($rows[0])): foreach ($rows[0] as $col=> $val):?>
            <th><?php echo $col;?></th>
        <?php endforeach; endif;?>
        </tr>
        <?php foreach($rows as $row):?>
            <tr>
                <?php foreach ($row as $key=>$val):?>
                    <td><?php echo ($key == 'utime' || $key == 'trade_date')? date('d.m.Y H:i:s', $val) : $val?></td>
                <?php endforeach;?>
            </tr>
        <?php endforeach;?>
    </table>

<?php endforeach;?>