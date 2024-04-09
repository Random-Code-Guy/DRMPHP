<?php
include "_config.php";

// Initialize variables
$ID = $_POST["ChanID"];
$Chan = $App->GetChannel($ID);
$Variants = $App->GetVariants($ID);
$Status = $Chan["Status"];

// Set default values
$Display1 = "none";
$Disabled1 = "disabled";
$Display2 = "none";
$Disabled2 = "disabled";
$Msg = "";

// Set display and disabled values based on channel status
switch ($Status) {
    case "Download":
        $Msg = "Channel is downloading. Stop the channel to make changes.";
        break;
    case "Not Supported":
        $Display1 = "";
        $Disabled1 = "";
        $Display2 = "";
        $Disabled2 = "";
        $Msg = "Channel manifest is not supported.";
        break;
    case "Offline":
        $Msg = "Channel is offline.";
        break;
    case "Error":
        $Display1 = "";
        $Disabled1 = "";
        $Display2 = "";
        $Disabled2 = "";
        $Msg = "Error reading manifest.";
        break;
    default:
        $Display1 = "";
        $Disabled1 = "";
        $Display2 = "";
        $Disabled2 = "";
        break;
}
?>

Select the variant you want to download:
<div class="input-append">
    <select <?php echo $Disabled1; ?> id="Variant">
        <option value="0">-- Select variant --</option>
        <?php foreach ($Variants as $V) : ?>
            <?php
            $AudioID = $V["AudioID"];
            $VideoID = $V["VideoID"];
            $Selected = ($AudioID == $Chan["AudioID"] && $VideoID == $Chan["VideoID"]) ? "selected" : "";
            ?>
            <option <?php echo $Selected; ?> value="<?php echo ($AudioID . "|" . $VideoID); ?>">
                L: <?php echo $V["Language"]; ?>, A: <?=$V["AudioID"] . " " . $V["AudioBandwidth"]; ?>, V: <?=$V["VideoID"] . " " . $V["VideoBandwidth"]; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <a <?php echo $Disabled1; ?> style="display:<?=$Display1; ?>" class="btn btn-success btn-sm" href="javascript: void(0)" onclick="save()">Save</a>
    <a <?php echo $Disabled2; ?> style="display:<?php echo $Display2; ?>" class="btn btn-warning btn-sm" href="javascript: void(0)" onclick="Parse()">Parse</a>
    <a class="btn btn-light btn-sm" href="javascript: void(0)" onclick="Cancel()">X</a>
</div>

<?php if ($Msg) : ?>
    <div class="alert alert-danger"><?php echo $Msg; ?></div>
<?php endif; ?>

<script>
function Parse() {
    var chanid = '<?php echo $ID; ?>';
    $.post("_func.php", {
            Func: "Parse",
            ID: chanid
        })
        .done(function(data) {
            $('#Config_' + chanid).load("_chanconfig.php", {
                ChanID: chanid
            });
        });
}

function save() {
    $.post("_func.php", {
            Func: "SaveVariant",
            Variant: $('#Variant').val(),
            ChanID: '<?php echo $ID; ?>'
        })
        .done(function() {
            $('div[id^="Config_"]').empty().hide();
            window.location.reload();
        });
}

function Cancel() {
    $('td[id^="Config_"]').empty().hide();
}
</script>