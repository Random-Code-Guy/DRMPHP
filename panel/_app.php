<?php

class App {
    public $DB;
    public $Config;

    public function __construct() {
        include "_db.php";
        try {
            $db = new PDO('mysql:host=' . $DBHost . ';dbname=' . $DBName . ';charset=utf8', $DBUser, $DBPass);
            $this->DB = $db;
        } catch (PDOException $e) {
            die("Error!: " . $e->getMessage()); // Instead of printing and exiting, you can throw an exception or handle it differently.
        }
        $this->ReadConfig();
    }

    public function ReadConfig() {
        $sql = "SELECT * FROM config ORDER BY ID";
        $statement = $this->DB->prepare($sql);
        $statement->execute();
        $this->Config = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function GetConfig($ConfigName) {
        foreach ($this->Config as $config) {
            if ($config["ConfigName"] === $ConfigName) {
                return $config["ConfigValue"];
            }
        }
        return null; // Return null if config value not found
    }

    public function isLoggedIn() {
        return isset($_SESSION["User"]["ID"]) && intval($_SESSION["User"]["ID"]) > 0;
    }

    public function getCurrentUserId() {
        return isset($_SESSION['User']['ID']) ? $_SESSION['User']['ID'] : null;
    }

    public function Login($UserID, $Password) {
        try {
            // Retrieve the user's stored password from the database
            $sql = "SELECT * FROM users WHERE UserID = :UserID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":UserID", $UserID);
            $st->execute();
            $user = $st->fetch();

            if ($user && $user["Password"] == $Password) {
                // Update last access time
                $this->updateLastAccess($UserID);
                return $user;
            } else {
                return false; // Incorrect credentials
            }
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            return false; // Login failed
        }
    }

    private function updateLastAccess($UserID) {
        try {
            // Update last access time
            $sql = "UPDATE users SET LastAccess = :LastAccess WHERE UserID = :UserID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":LastAccess", date("Y-m-d H:i:s"));
            $st->bindParam(":UserID", $UserID);
            $st->execute();
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            // You may choose to log this error or handle it differently based on your application's requirements
        }
    }

    public function ChangePassword($UserID, $CurrentPassword, $NewPassword) {
        try {
            // Retrieve the user's stored password from the database
            $sql = "SELECT Password FROM users WHERE UserID = :UserID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":UserID", $UserID);
            $st->execute();
            $line = $st->fetch();

            if ($line && $line["Password"] === $CurrentPassword) {
                // Update the password
                $sql = "UPDATE users SET Password = :NewPassword, LastAccess = :LastAccess WHERE UserID = :UserID";
                $st = $this->DB->prepare($sql);
                $st->bindParam(":NewPassword", $NewPassword);
                $st->bindParam(":LastAccess", date("Y-m-d H:i:s"));
                $st->bindParam(":UserID", $UserID);
                $st->execute();

                return true; // Password change successful
            } else {
                return false; // Current password is incorrect
            }
        } catch (PDOException $e) {
            // Handle the database error
            error_log("Database Error: " . $e->getMessage());
            return false; // Password change failed
        }
    }

    public function GetChannel($ID) {
        $channel = [];
        
        try {
            // Fetch basic channel info
            $sql = "SELECT * FROM channels WHERE ID = :ID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":ID", $ID);
            $st->execute();
            $channel = $st->fetch();

            // Fetch related data
            $relatedDataQueries = [
                ["field" => "AudioIDs", "query" => "SELECT DISTINCT AudioID FROM variant WHERE ChannelID = :ChannelID"],
                ["field" => "VideoIDs", "query" => "SELECT DISTINCT VideoID FROM variant WHERE ChannelID = :ChannelID"],
                ["field" => "Keys", "query" => "SELECT * FROM channel_keys WHERE ChannelID = :ChannelID"],
                ["field" => "CustomHeaders", "query" => "SELECT * FROM channel_headers WHERE ChannelID = :ChannelID"]
            ];

            foreach ($relatedDataQueries as $queryInfo) {
                $field = $queryInfo["field"];
                $query = $queryInfo["query"];
                $stmt = $this->DB->prepare($query);
                $stmt->bindParam(":ChannelID", $ID);
                $stmt->execute();
                $channel[$field] = $stmt->fetchAll();
            }

            // Process AllowedIP
            $tmp = json_decode($channel["AllowedIP"], true);
            $channel["AllowedIP"] = implode("\r\n", $tmp);
        } catch (PDOException $e) {
            // Handle database errors
            // Log or throw exception as appropriate
            // Example: logError($e->getMessage());
            // throw $e;
        }

        return $channel;
    }

    public function GetChannelByName($ChName) {
        try {
            // Retrieve channel information
            $sql = "SELECT * FROM channels WHERE REPLACE(ChannelName, ' ', '_') = :ChName";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":ChName", $ChName);
            $st->execute();
            $channel = $st->fetch();

            if ($channel) {
                // Process AllowedIP
                $tmp = json_decode($channel["AllowedIP"], true);
                $channel["AllowedIP"] = implode("\r\n", $tmp);

                // Retrieve audio and video IDs
                $variantSql = "SELECT DISTINCT AudioID, VideoID FROM variant WHERE ChannelID = :ChannelID";
                $variantSt = $this->DB->prepare($variantSql);
                $variantSt->bindParam(":ChannelID", $channel["ID"]);
                $variantSt->execute();
                $channel["AudioIDs"] = $variantSt->fetchAll(PDO::FETCH_COLUMN, 0); // Fetch audio IDs
                $channel["VideoIDs"] = $variantSt->fetchAll(PDO::FETCH_COLUMN, 1); // Fetch video IDs

                // Retrieve keys
                $keySql = "SELECT * FROM channel_keys WHERE ChannelID = :ChannelID";
                $keySt = $this->DB->prepare($keySql);
                $keySt->bindParam(":ChannelID", $channel["ID"]);
                $keySt->execute();
                $channel["Keys"] = $keySt->fetchAll();

                // Retrieve custom headers
                $headerSql = "SELECT * FROM channel_headers WHERE ChannelID = :ChannelID";
                $headerSt = $this->DB->prepare($headerSql);
                $headerSt->bindParam(":ChannelID", $channel["ID"]);
                $headerSt->execute();
                $channel["CustomHeaders"] = $headerSt->fetchAll();

                return $channel;
            } else {
                return null; // Channel not found
            }
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null or handle the error as appropriate
        }
    }

    public function GetVariants($ChannelID) {
        try {
            // Define the SQL query
            $sql = "SELECT VariantID, AudioID, VideoID FROM variant WHERE ChannelID = :ChannelID ORDER BY AudioID, VideoID";

            // Prepare and execute the query
            $st = $this->DB->prepare($sql);
            $st->bindParam(":ChannelID", $ChannelID);
            $st->execute();

            // Fetch only necessary columns
            $Variants = $st->fetchAll(PDO::FETCH_ASSOC);

            return $Variants;
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            return []; // Return an empty array or handle the error as appropriate
        }
    }

    public function GetAudioIDs($ChannelID) {
        try {
            // Define the SQL query
            $sql = "SELECT DISTINCT AudioID, Language FROM variant WHERE ChannelID = :ChannelID";

            // Prepare and execute the query
            $st = $this->DB->prepare($sql);
            $st->bindParam(":ChannelID", $ChannelID);
            $st->execute();

            // Fetch only necessary columns
            $audioIDs = $st->fetchAll(PDO::FETCH_ASSOC);

            return $audioIDs;
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            return []; // Return an empty array or handle the error as appropriate
        }
    }


    public function GetAllChannels($Search = null) {
        try {
            $conditions = [];
            $parameters = [];

            if ($Search !== null && !empty($Search["search"])) {
                if (!empty($Search["SearchChanName"])) {
                    $conditions[] = "ChannelName LIKE ?";
                    $parameters[] = '%' . $Search["SearchChanName"] . '%';
                }
                if (!empty($Search["SearchCatName"])) {
                    $conditions[] = "CatName LIKE ?";
                    $parameters[] = '%' . $Search["SearchCatName"] . '%';
                }
                if (!empty($Search["SearchMPDUrl"])) {
                    $conditions[] = "Manifest LIKE ?";
                    $parameters[] = '%' . $Search["SearchMPDUrl"] . '%';
                }
            }

            // Construct the WHERE clause
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

            // Construct the SQL query
            $sql = "SELECT channels.*, TIMEDIFF(NOW(), channels.StartTime) AS Uptime, cats.CatName
                    FROM channels
                    INNER JOIN cats ON channels.CatID = cats.CatID
                    $whereClause
                    ORDER BY ID";

            // Prepare and execute the query
            $st = $this->DB->prepare($sql);
            $st->execute($parameters);

            // Fetch the result
            return $st->fetchAll();
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            return []; // Return an empty array or handle the error as appropriate
        }
    }

    public function SaveChannel($Data) {
        try {
            // Convert data to appropriate types
            $ID = intval($Data["ID"]);
            $ChannelName = $Data["ChannelName"];
            $Manifest = $Data["Manifest"];
            $CatId = intval($Data["CatID"]);
            $KID = $Data["KID"];
            $Key = $Data["Key"];
            $CustomHeaders = $Data["customHeaders"];
            $AllowedIP = explode("\r\n", $Data["AllowedIP"]);
            $AllowedIPJson = json_encode($AllowedIP);
            $AutoRestart = intval($Data["AutoRestart"]);
            $AudioIDs = implode(",", $Data["AudioIDs"]);
            $Output = "hls";

            $UseProxy = intval($Data["UseProxy"]);
            $ProxyURL = $Data["ProxyURL"];
            $ProxyPort = intval($Data["ProxyPort"]);
            $ProxyUser = $Data["ProxyUser"];
            $ProxyPass = $Data["ProxyPass"];

            $DownloadUseragent = $Data["DownloadUseragent"];
            $VideoID = $Data["VideoID"];

            $SegmentJoiner = max(3, intval($Data["SegmentJoiner"]));
            $PlaylistLimit = max(3, intval($Data["PlaylistLimit"]));
            $URLListLimit = max(1, intval($Data["URLListLimit"]));

            // Check if KID and Key counts match
            if (count($KID) != count($Key)) {
                return "KID and Key count not match";
            }

            $isInsert = $ID == 0;

            if ($isInsert) {
                // Insert new channel
                $sql = "INSERT INTO channels (ChannelName, Manifest, CatId, SegmentJoiner, PlaylistLimit, URLListLimit, DownloadUseragent, AudioID, VideoID, AllowedIP, Output, UseProxy, ProxyURL, ProxyPort, ProxyUser, ProxyPass) VALUES (:ChannelName, :Manifest, :CatId, :SegmentJoiner, :PlaylistLimit, :URLListLimit, :DownloadUseragent, :AudioID, :VideoID, :AllowedIP, :Output, :UseProxy, :ProxyURL, :ProxyPort, :ProxyUser, :ProxyPass)";
            } else {
                // Update existing channel
                $sql = "UPDATE channels SET ChannelName = :ChannelName, Manifest = :Manifest, CatId = :CatId, SegmentJoiner = :SegmentJoiner, PlaylistLimit = :PlaylistLimit, URLListLimit = :URLListLimit, DownloadUseragent = :DownloadUseragent, AudioID = :AudioID, VideoID = :VideoID, AllowedIP = :AllowedIP, Output = :Output, UseProxy = :UseProxy, ProxyURL = :ProxyURL, ProxyPort = :ProxyPort, ProxyUser = :ProxyUser, ProxyPass = :ProxyPass, AutoRestart = :AutoRestart WHERE ID = :ID";
            }

            // Prepare and execute the query
            $st = $this->DB->prepare($sql);
            $st->bindParam(":ChannelName", $ChannelName);
            $st->bindParam(":Manifest", $Manifest);
            $st->bindParam(":CatId", $CatId);
            $st->bindParam(":SegmentJoiner", $SegmentJoiner);
            $st->bindParam(":PlaylistLimit", $PlaylistLimit);
            $st->bindParam(":URLListLimit", $URLListLimit);
            $st->bindParam(":DownloadUseragent", $DownloadUseragent);
            $st->bindParam(":AudioID", $AudioIDs);
            $st->bindParam(":VideoID", $VideoID);
            $st->bindParam(":AllowedIP", $AllowedIPJson);
            $st->bindParam(":Output", $Output);
            $st->bindParam(":UseProxy", $UseProxy);
            $st->bindParam(":ProxyURL", $ProxyURL);
            $st->bindParam(":ProxyPort", $ProxyPort);
            $st->bindParam(":ProxyUser", $ProxyUser);
            $st->bindParam(":ProxyPass", $ProxyPass);
            $st->bindParam(":AutoRestart", $AutoRestart);

            if ($isInsert) {
                $st->execute();
                $ID = $this->DB->lastInsertId();

                if ($ID == 0) {
                    return "Error while inserting channel";
                }
            } else {
                $st->bindParam(":ID", $ID);
                $st->execute();
            }

            // Insert or update keys
            $this->InsertOrUpdateKeys($ID, $KID, $Key);

            // Insert or update custom headers
            $this->InsertOrUpdateHeaders($ID, $CustomHeaders);

            // Perform additional operations if it's an update
            if (!$isInsert) {
                // Check if Manifest field has changed
                if ($Old["Manifest"] != $Manifest) {
                    $Data["ChanID"] = $ID;
                    $this->StopDownload($Data);
                    $this->Parse($ID);
                }
            }

            return $ID;
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Database Error: " . $e->getMessage());
            return false; // Return false or handle the error as appropriate
        }
    }

    // Function to insert or update keys
    private function InsertOrUpdateKeys($channelID, $KID, $Key) {
        $sql = "INSERT INTO channel_keys (ChannelID, KID, `Key`) VALUES (:ChannelID, :KID, :Key)";
        $st = $this->DB->prepare($sql);
        for ($i = 0; $i < count($KID); $i++) {
            if ($KID[$i] == "" || $Key[$i] == "") {
                continue;
            }

            $st->bindParam(":ChannelID", $channelID);
            $st->bindParam(":KID", $KID[$i]);
            $st->bindParam(":Key", $Key[$i]);
            $st->execute();
        }
    }

    // Function to insert or update custom headers
    private function InsertOrUpdateHeaders($channelID, $CustomHeaders) {
        $sql = "INSERT INTO channel_headers (ChannelID, `Value`) VALUES (:ChannelID, :Value)";
        $st = $this->DB->prepare($sql);
        foreach ($CustomHeaders as $header) {
            if ($header == "") {
                continue;
            }

            $st->bindParam(":ChannelID", $channelID);
            $st->bindParam(":Value", $header);
            $st->execute();
        }
    }

    public function Parse($ID) {
        try {
            // Retrieve channel data
            $Data = $this->GetChannel($ID);

            // Determine whether to use proxy
            $UseProxy = intval($Data["UseProxy"]) == 1;
            $ProxyURL = $UseProxy ? $Data["ProxyURL"] ?? $this->GetConfig("ProxyURL") : '';
            $ProxyPort = $UseProxy ? $Data["ProxyPort"] ?? $this->GetConfig("ProxyPort") : '';
            $ProxyUser = $UseProxy ? $Data["ProxyUser"] ?? $this->GetConfig("ProxyUser") : '';
            $ProxyPass = $UseProxy ? $Data["ProxyPass"] ?? $this->GetConfig("ProxyPass") : '';

            // Build command
            $cmd = "php downloader.php --mode=infoshort --chid=$ID";
            if ($UseProxy) {
                $cmd .= " --proxyurl=$ProxyURL --proxyport=$ProxyPort --proxyuser=$ProxyUser --proxypass=$ProxyPass";
            }

            // Execute command
            exec($cmd, $Res);

            // Parse results
            $Variants = [];
            foreach ($Res as $line) {
                $fields = explode("|", $line);
                $Variants[] = [
                    "Language" => $fields[0],
                    "Bandwidth" => "0",
                    "AudioID" => $fields[1],
                    "AudioBandwidth" => $fields[2],
                    "AudioCodecs" => $fields[3],
                    "VideoID" => $fields[4],
                    "VideoBandwidth" => $fields[5],
                    "VideoCodecs" => $fields[6],
                    "Width" => $fields[7],
                    "Height" => $fields[8],
                    "Framerate" => $fields[9]
                ];
            }

            // Update channel variants
            $Data["ChanID"] = $ID;
            $Data["Variants"] = $Variants;
            $this->UpdateChanVariants($Data);
        } catch (Exception $e) {
            // Handle any exceptions
            error_log("Error parsing channel: " . $e->getMessage());
        }
    }

    public function UpdateChanVariants($Data) {
        try {
            $ChanID = $Data["ChanID"];
            $Variants = $Data["Variants"];

            // Begin transaction
            $this->DB->beginTransaction();

            // Delete existing variants for the channel
            $sqlDelete = "DELETE FROM variant WHERE ChannelID = :ChannelID";
            $stDelete = $this->DB->prepare($sqlDelete);
            $stDelete->bindParam(":ChannelID", $ChanID);
            $stDelete->execute();

            // Insert new variants
            $sqlInsert = "INSERT INTO variant (
                ChannelID, Language, Bandwidth, AudioID, AudioBandwidth, AudioCodecs, VideoID, VideoBandwidth, VideoCodecs, Width, Height, Framerate
            ) VALUES (
                :ChannelID, :Language, :Bandwidth, :AudioID, :AudioBandwidth, :AudioCodecs, :VideoID, :VideoBandwidth, :VideoCodecs, :Width, :Height, :Framerate
            )";
            $stInsert = $this->DB->prepare($sqlInsert);
            foreach ($Variants as $Variant) {
                $stInsert->bindParam(":ChannelID", $ChanID);
                $stInsert->bindParam(":Language", $Variant["Language"]);
                $stInsert->bindParam(":Bandwidth", $Variant["Bandwidth"]);
                $stInsert->bindParam(":AudioID", $Variant["AudioID"]);
                $stInsert->bindParam(":AudioBandwidth", $Variant["AudioBandwidth"]);
                $stInsert->bindParam(":AudioCodecs", $Variant["AudioCodecs"]);
                $stInsert->bindParam(":VideoID", $Variant["VideoID"]);
                $stInsert->bindParam(":VideoBandwidth", $Variant["VideoBandwidth"]);
                $stInsert->bindParam(":VideoCodecs", $Variant["VideoCodecs"]);
                $stInsert->bindParam(":Width", $Variant["Width"]);
                $stInsert->bindParam(":Height", $Variant["Height"]);
                $stInsert->bindParam(":Framerate", $Variant["Framerate"]);
                $stInsert->execute();
            }

            // Check and update channel if old audio and video IDs are not present in the variants
            $sqlCheck = "SELECT ID FROM variant WHERE ChannelID = :ChannelID AND AudioID = :AudioID AND VideoID = :VideoID";
            $stCheck = $this->DB->prepare($sqlCheck);
            $stCheck->bindParam(":ChannelID", $ChanID);
            $stCheck->bindParam(":AudioID", $Data["AudioID"]);
            $stCheck->bindParam(":VideoID", $Data["VideoID"]);
            $stCheck->execute();
            $line = $stCheck->fetch();

            if (!$line) {
                $sqlUpdate = "UPDATE channels SET AudioID = '', VideoID = '' WHERE ID = :ChanID";
                $stUpdate = $this->DB->prepare($sqlUpdate);
                $stUpdate->bindParam(":ChanID", $ChanID);
                $stUpdate->execute();
            }

            // Commit transaction
            $this->DB->commit();
        } catch (PDOException $e) {
            // Roll back transaction if an error occurs
            $this->DB->rollBack();
            error_log("Error updating channel variants: " . $e->getMessage());
        }
    }

    public function SaveVariant($Data) {
        try {
            // Extract data from input
            $ID = $Data["ChanID"];
            $Variant = $Data["Variant"];
            $tmp = explode("|", $Variant);
            $AudioID = $tmp[0];
            $VideoID = $tmp[1];

            // Update channel with new AudioID and VideoID
            $sql = "UPDATE channels SET AudioID = :AudioID, VideoID = :VideoID WHERE ID = :ID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":AudioID", $AudioID);
            $st->bindParam(":VideoID", $VideoID);
            $st->bindParam(":ID", $ID);
            $st->execute();
        } catch (PDOException $e) {
            // Handle any database errors
            error_log("Error saving variant: " . $e->getMessage());
        }
    }

    public function StartDownload($Data) {
        try {
            $ChanID = $Data["ChanID"];
            $DownloaderPath = $this->GetConfig("DownloaderPath");
            $ChannData = $this->GetChannel($ChanID);
            $UseProxy = intval($ChannData["UseProxy"]) == 1;

            // Set proxy configuration if necessary
            if ($UseProxy) {
                $ProxyURL = $ChannData["ProxyURL"] ?: $this->GetConfig("ProxyURL");
                $ProxyPort = $ChannData["ProxyPort"] ?: $this->GetConfig("ProxyPort");
                $ProxyUser = $ChannData["ProxyUser"] ?: $this->GetConfig("ProxyUser");
                $ProxyPass = $ChannData["ProxyPass"] ?: $this->GetConfig("ProxyPass");
                $proxyParams = "--proxyurl=$ProxyURL --proxyport=$ProxyPort --proxyuser=$ProxyUser --proxypass=$ProxyPass";
            } else {
                $proxyParams = "";
            }

            // Construct the command to start download
            $cmd = "sudo php $DownloaderPath/downloader.php --mode=download --chid=$ChanID $proxyParams --checkkey=1";

            // Execute the command in background
            $this->execInBackground($cmd);

            // Sleep for a short while to ensure the process starts before returning
            sleep(1);
        } catch (Exception $e) {
            // Handle any exceptions
            error_log("Error starting download: " . $e->getMessage());
        }
    }

    public function execInBackground($cmd) {
        // Check if the operating system is Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // For Windows, use `start` command to execute the command in the background
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            // For Unix-like systems, append `&` to the command to run it in the background and redirect output to null
            exec($cmd . " > /dev/null 2>&1 &");
        }
    }

    public function StopDownload($Data) {
        try {
            $ChanID = $Data["ChanID"];
            $channelData = $this->GetChannel($ChanID);
            $PID = $channelData["PID"];
            $FPID = $channelData["FPID"];

            // Stop the primary process if PID exists
            if ($PID) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    exec("taskkill /F /PID $PID");
                } else {
                    exec("sudo kill -9 $PID");
                }
            }

            // Stop the fallback process if FPID exists
            if ($FPID) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    exec("taskkill /F /PID $FPID");
                } else {
                    exec("sudo kill -9 $FPID");
                }
            }

            // Update channel status and reset related fields
            $Status = "Stopped";
            $sql = "UPDATE channels SET Status=:Status, PID=0, FPID=0, info='', StartTime=null, EndTime=NOW() WHERE ID=:ID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":ID", $ChanID);
            $st->bindParam(":Status", $Status);
            $st->execute();

            // Clean up temporary files
            $WorkPath = $this->GetConfig("DownloadPath") . "/" . str_replace(" ", "_", $channelData["ChannelName"]);
            $this->cleanupDirectory($WorkPath);
        } catch (Exception $e) {
            // Handle any exceptions
            error_log("Error stopping download: " . $e->getMessage());
        }
    }

    // Function to recursively clean up a directory
    private function cleanupDirectory($directory) {
        $files = glob($directory . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->cleanupDirectory($file) : unlink($file);
        }
        rmdir($directory);
    }

    public function SaveSettings($Data) {
        try {
            $sql = "UPDATE config SET ConfigValue = :Value WHERE ConfigName = :Key";
            $st = $this->DB->prepare($sql);
            
            foreach ($Data as $Key => $Value) {
                $st->bindParam(":Key", $Key);
                $st->bindParam(":Value", $Value);
                $st->execute();
            }

            $this->ReadConfig();
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Error saving settings: " . $e->getMessage());
        }
    }

    public function DeleteChannel($ID) {
        try {
            $this->StopDownload(["ChanID" => $ID]);

            $sqlChannels = "DELETE FROM channels WHERE ID = :ID";
            $sqlVariant = "DELETE FROM variant WHERE ChannelID = :ID";
            $sqlKeys = "DELETE FROM channel_keys WHERE ChannelID = :ID";
            $sqlHeaders = "DELETE FROM channel_headers WHERE ChannelID = :ID";

            $stChannels = $this->DB->prepare($sqlChannels);
            $stVariant = $this->DB->prepare($sqlVariant);
            $stKeys = $this->DB->prepare($sqlKeys);
            $stHeaders = $this->DB->prepare($sqlHeaders);

            $stChannels->bindParam(":ID", $ID);
            $stVariant->bindParam(":ID", $ID);
            $stKeys->bindParam(":ID", $ID);
            $stHeaders->bindParam(":ID", $ID);

            $stChannels->execute();
            $stVariant->execute();
            $stKeys->execute();
            $stHeaders->execute();
        } catch (PDOException $e) {
            // Handle database errors
            error_log("Error deleting channel: " . $e->getMessage());
        }
    }

    public function All($Action) {
        $channels = $this->GetAllChannels();

        foreach ($channels as $channel) {
            $data["ChanID"] = $channel["ID"];
            if ($Action == "Start" && $channel["Status"] == "Stopped") {
                $this->StartDownload($data);
            } elseif ($Action == "Stop") {
                $this->StopDownload($data);
            }
        }
    }

    public function TestMPD($Data) {
        $Url = $Data["MPD"];
        $UseProxy = $Data["UseProxy"] == "true";
        $Useragent = $Data["Useragent"] ?: $this->GetConfig("DownloadUseragent");
        $data = [];

        if ($UseProxy) {
            $ProxyURL = $Data["ProxyURL"] ?: $this->GetConfig("ProxyURL");
            $ProxyPort = $Data["ProxyPort"] ?: $this->GetConfig("ProxyPort");
            $ProxyUser = $Data["ProxyUser"] ?: $this->GetConfig("ProxyUser");
            $ProxyPass = $Data["ProxyPass"] ?: $this->GetConfig("ProxyPass");

            $cmd = 'php downloader.php --mode=testonly --mpdurl="' . $Url . '" --proxyurl="' . $ProxyURL . '" --proxyport="' . $ProxyPort . '" --proxyuser="' . $ProxyUser . '" --proxypass="' . $ProxyPass . '" --useragent="' . $Useragent . '"';
        } else {
            $cmd = 'php downloader.php --mode=testonly --mpdurl="' . $Url . '"';
        }
        exec($cmd, $Res);
        $data["str"] = implode("\r\n", $Res);

        $Res = null;
        if ($UseProxy) {
            $ProxyURL = $Data["ProxyURL"] ?: $this->GetConfig("ProxyURL");
            $ProxyPort = $Data["ProxyPort"] ?: $this->GetConfig("ProxyPort");
            $ProxyUser = $Data["ProxyUser"] ?: $this->GetConfig("ProxyUser");
            $ProxyPass = $Data["ProxyPass"] ?: $this->GetConfig("ProxyPass");

            $cmd = 'php downloader.php --mode=infojson --mpdurl="' . $Url . '"  --proxyurl="' . $ProxyURL . '" --proxyport="' . $ProxyPort . '" --proxyuser="' . $ProxyUser . '" --proxypass="' . $ProxyPass . '" --useragent="' . $Useragent . '"';
        } else {
            $cmd = 'php downloader.php --mode=infojson --mpdurl="' . $Url . '"';
        }
        exec($cmd, $Res);
        if (!empty($Res[0])) {
            $x = json_decode($Res[0], true);
            $data["a"] = $x["a"] ?? '';
            $data["v"] = $x["v"] ?? '';
        } else {
            $data["a"] = '';
            $data["v"] = '';
        }
        return json_encode($data);
    }

    public function GetLog($ID, $Lines) {
        $data = $this->GetChannel($ID);
        $ChName = str_replace(" ", "_", $data["ChannelName"]);
        $WorkPath = $this->GetConfig("DownloadPath");
        $logs = [];

        // Get logs for ffmpeg
        $ffmpegLogFile = $WorkPath . "/" . $ChName . "/log/ffmpeg.log";
        $logs['ffmpeg'] = $this->tail($ffmpegLogFile, $Lines);

        // Get logs for PHP
        $phpLogFile = $WorkPath . "/" . $ChName . "/log/php.log";
        $logs['php'] = $this->tail($phpLogFile, $Lines);

        return $logs;
    }

    public function tail($filepath, $lines = 1, $adaptive = true) {
        $fileHandle = @fopen($filepath, "rb");
        if ($fileHandle === false) {
            return false; // Failed to open the file
        }

        // Set buffer size based on number of lines requested
        if (!$adaptive) {
            $bufferSize = 4096;
        } else {
            $bufferSize = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        }

        // Move file pointer to the end
        fseek($fileHandle, -1, SEEK_END);
        if (fread($fileHandle, 1) != "\n") {
            $lines--; // Adjust line count if last character is not a newline
        }

        // Read lines from the end of the file
        $output = '';
        while (ftell($fileHandle) > 0 && $lines >= 0) {
            $seek = min(ftell($fileHandle), $bufferSize);
            fseek($fileHandle, -$seek, SEEK_CUR);
            $chunk = fread($fileHandle, $seek);
            $output = $chunk . $output;
            fseek($fileHandle, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            $lines -= substr_count($chunk, "\n");
        }

        // Trim excess lines
        while ($lines++ < 0) {
            $output = substr($output, strpos($output, "\n") + 1);
        }

        fclose($fileHandle); // Close the file handle
        return trim($output);
    }

    public function GetChanStat() {
        $sql = "SELECT ID, TIMEDIFF(now(), StartTime) AS Uptime, info, status, PID, FPID FROM channels";
        $st = $this->DB->prepare($sql);
        $st->execute();
        $data = $st->fetchAll();

        $stats = [];
        foreach ($data as $channel) {
            $x = [];
            $x["id"] = $channel["ID"];
            $x["status"] = $channel["status"];
            $Info = json_decode($channel["info"], true);
            $x["uptime"] = $channel["Uptime"];
            $x["pid"] = $channel["PID"];
            $x["fpid"] = $channel["FPID"];
            $x["pidexist"] = file_exists("/proc/" . $channel["PID"]) ? 1 : 0;
            $x["fpidexist"] = file_exists("/proc/" . $channel["FPID"]) ? 1 : 0;
            if ($x["status"] == "Downloading") {
                $x["bitrate"] = isset($Info["bitrate"]) ? round($Info["bitrate"] / 1000, 1) . "kb" : "";
                $x["codecs"] = isset($Info["vcodec"]) && isset($Info["acodec"]) ? $Info["vcodec"] . "/" . $Info["acodec"] : "";
                $x["res"] = isset($Info["width"]) && isset($Info["height"]) ? $Info["width"] . "x" . $Info["height"] : "";
                $x["framerate"] = isset($Info["framerate"]) ? str_replace("/1", "", $Info["framerate"]) : "";
            } else {
                $x["bitrate"] = "";
                $x["codecs"] = "";
                $x["res"] = "";
                $x["framerate"] = "";
            }
            $stats[] = $x;
        }
        return json_encode($stats);
    }

    public function AllowedIP($ChID, $IP) {
        $channelData = $this->GetChannel($ChID);
        $allowedIPs = json_decode($channelData["AllowedIPJson"], true);
        
        // Check if the IP is in the allowed IPs list
        return in_array('*', $allowedIPs) || in_array(strtolower($IP), $allowedIPs);
    }

    public function BackupDatabase() {
        try {
            include "_db.php";
            $folderName = $this->GetConfig("BackupPath");
            
            // Create backup directory if it doesn't exist
            if (!file_exists($folderName)) {
                mkdir($folderName, 0777, true);
            }
            
            // Change ownership and permissions of the backup directory
            chown($folderName, 'www-data');
            chmod($folderName, 0777);

            $fileName = $DBName . "_" . date("Y-m-d_H:i:s") . ".sql";
            $filePath = $folderName . "/" . $fileName;
            
            // Execute mysqldump command to create backup
            exec("mysqldump --add-drop-table -u $DBUser -p'$DBPass' $DBName > $filePath");
            
            // Read the SQL file content
            $sqlContent = file_get_contents($filePath);

            // Return the folder name and file name
            return [$folderName, $fileName];
        } catch (Exception $e) {
            // Return error message if an exception occurs
            return ["Error", $e->getMessage()];
        }
    }

    public function RestoreBackup($fileName) {
        try {
            include "_db.php";

            $backupFolder = $this->GetConfig("BackupPath");
            $filePath = $backupFolder . "/" . $fileName; 
            // Check if backup file exists
            if (!file_exists($filePath)) {
                throw new Exception("Backup file not found.");
            }
            
            // Extract database name from backup file name
            $fileNameParts = explode('_', basename($filePath));
            $dbName = reset($fileNameParts);

            // Perform database restore
            $cmd = "mysql -u $DBUser -p'$DBPass' $dbName < $backupFilePath";
            exec($cmd);

            return "Database restored successfully.";
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    public function GetBackups() {
        $backupFolder = $this->GetConfig("BackupPath");
        $backupFiles = glob($backupFolder . "/*.sql");
        
        // Extract only the filenames without the folder path
        $backupFileNames = array_map(function($file) use ($backupFolder) {
            return basename($file);
        }, $backupFiles);
        
        return $backupFileNames;
    }

    public function DeleteBackup($fileName) {
        $backupFolder = $this->GetConfig("BackupPath");
        $filePath = $backupFolder . "/" . $fileName;

        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                return true; // Deleted successfully
            } else {
                return false; // Failed to delete
            }
        } else {
            return false; // File does not exist
        }
    }

    public function DownloadBackup($File) {
        file_put_contents("getbkup.txt", 1);
    }

    public function GetAllCats() {
        $sql = "SELECT cats.CatID, cats.CatName, COUNT(channels.ID) AS ChannelsCount
                FROM cats
                LEFT JOIN channels ON cats.CatID = channels.CatID
                GROUP BY cats.CatID, cats.CatName
                ORDER BY cats.CatID";

        $st = $this->DB->prepare($sql);
        $st->execute();
        
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function GetCat($ID) {
        $sql = "SELECT * FROM cats WHERE CatID = :CatID";
        $st = $this->DB->prepare($sql);
        $st->bindParam(":CatID", $ID, PDO::PARAM_INT);
        $st->execute();
        
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function SaveCat($Data) {
        $ID = isset($Data["ID"]) ? intval($Data["ID"]) : 0;
        $CatName = $Data["CatName"];

        if ($ID > 0) {
            $sql = "UPDATE cats SET CatName = :CatName WHERE CatID = :CatID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":CatID", $ID, PDO::PARAM_INT);
            $st->bindParam(":CatName", $CatName);
            $st->execute();
        } else {
            $sql = "INSERT INTO cats (CatName) VALUES (:CatName)";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":CatName", $CatName);
            $st->execute();
            $ID = $this->DB->lastInsertId();
        }

        return $ID;
    }

    public function DeleteCat($ID) {
        if ($ID != 1) {
            $sql = "DELETE FROM cats WHERE CatID = :CatID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":CatID", $ID);
            $st->execute();

            $sql = "UPDATE channels SET CatID = 1 WHERE CatID = :CatID";
            $st = $this->DB->prepare($sql);
            $st->bindParam(":CatID", $ID);
            $st->execute();

            // Check if there are any remaining categories
            $remainingCats = $this->GetAllCats();
            if (empty($remainingCats)) {
                $sql = "INSERT INTO cats (CatID, CatName) VALUES (1, 'Uncategorized')";
                $st = $this->DB->prepare($sql);
                $st->execute();
            }
        }
    }

    public function GetStat() {
        $sql = "SELECT Status, ChannelName, IFNULL(TIME_TO_SEC(TIMEDIFF(NOW(), channels.StartTime)), 0)/60 AS Uptime FROM channels";
        $st = $this->DB->prepare($sql);
        $st->execute();
        $data = $st->fetchAll();

        $totalChannels = count($data);
        $onlineChannels = 0;
        $offlineChannels = 0;
        $channelNames = [];
        $uptimeValues = [];

        foreach ($data as $channel) {
            $channelNames[] = $channel["ChannelName"];
            $uptimeValues[] = $channel["Uptime"];
            if ($channel["Status"] == "Downloading") {
                $onlineChannels++;
            } else {
                $offlineChannels++;
            }
        }

        return [
            "Total" => $totalChannels,
            "Online" => $onlineChannels,
            "Offline" => $offlineChannels,
            "Names" => implode(",", $channelNames),
            "Uptime" => implode(",", $uptimeValues)
        ];
    }

    public function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $string = [
            'y' => ['year', 'years'],
            'm' => ['month', 'months'],
            'w' => ['week', 'weeks'],
            'd' => ['day', 'days'],
            'h' => ['hour', 'hours'],
            'i' => ['minute', 'minutes'],
            's' => ['second', 'seconds']
        ];

        $elapsed = [];
        foreach ($string as $key => $value) {
            if ($diff->$key) {
                $elapsed[] = $diff->$key . ' ' . ($diff->$key > 1 ? $value[1] : $value[0]);
            }
        }

        $result = '';
        if ($full) {
            $result = implode(', ', $elapsed) . ' ago';
        } else {
            $result = $elapsed ? reset($elapsed) . ' ago' : 'just now';
        }

        return $result;
    }

    public function GetNotification($Status = "") {
        $condition = $Status === "" ? "" : " AND Status = :status";
        $sql = "SELECT * FROM notification WHERE 1 = 1 $condition";
        $st = $this->DB->prepare($sql);
        if ($Status !== "") {
            $st->bindParam(":status", $Status);
        }
        $st->execute();
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as &$notification) {
            $notification["ago"] = $this->time_elapsed_string($notification["Sent"]);
        }
        return $data;
    }

    public function SetNotiSeen($ID) {
        $sql = "UPDATE notification SET Status = 'Seen' WHERE ID = :id";
        $st = $this->DB->prepare($sql);
        $st->bindParam(":id", $ID);
        $st->execute();
    }

    public function GetFreeUDPIPs($ChID) {
        $allIPs = [];
        for ($j = 0; $j < 5; $j++) {
            for ($i = 1; $i < 256; $i++) {
                $allIPs[] = "239.200.$j.$i";
            }
        }

        $sql = "SELECT DISTINCT UDPIP AS IP FROM channels WHERE ID <> :chid";
        $st = $this->DB->prepare($sql);
        $st->bindParam(":chid", $ChID);
        $st->execute();
        $usedIPs = $st->fetchAll(PDO::FETCH_COLUMN);

        $freeIPs = array_diff($allIPs, $usedIPs);
        return array_values($freeIPs);
    }

    private function GetURL($URL, $UseProxy = false, $ProxyURL = "", $ProxyPort = "", $ProxyUser = "", $ProxyPass = "") {
        // Chrome user agent
        $userAgent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.134 Safari/537.36";
        
        $options = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Connection: Keep-Alive',
                'User-Agent: ' . $userAgent,
            ],
        ];
        
        if ($UseProxy) {
            $options[CURLOPT_PROXY] = $ProxyURL;
            $options[CURLOPT_PROXYPORT] = $ProxyPort;
            
            if (!empty($ProxyUser) && !empty($ProxyPass)) {
                $options[CURLOPT_PROXYUSERPWD] = "$ProxyUser:$ProxyPass";
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC | CURLAUTH_ANY;
            }
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $data = curl_exec($ch);
        
        if ($data === false) {
            // Handle cURL error
            $error = curl_error($ch);
            curl_close($ch);
            return $error;
        }
        
        curl_close($ch);
        return $data;
    }

    private function HexToBase64($string) {
        return base64_encode(hex2bin($string));
    }

    private function Base64ToHex($string) {
        return bin2hex(base64_decode($string));
    }

    private function IsValidWidevinePSSH($PSSH) {
        $psshHex = strtoupper($this->Base64ToHex($PSSH));
        $widevineId = "EDEF8BA979D64ACEA3C827DCD51D21ED";
        return strpos($psshHex, $widevineId) !== false;
    }


    private function ExtractKidFromPSSH($PSSH) {
        $kidArray = [];
        $psshHex = strtoupper($this->Base64ToHex($PSSH));
        $widevineId = "EDEF8BA979D64ACEA3C827DCD51D21ED";
        $widevineIdPos = strpos($psshHex, $widevineId);
        
        if ($widevineIdPos !== false) {
            $kidPos = $widevineIdPos + strlen($widevineId) + 8;
            $kidSplitter = "1210";
            $kidLength = 32;
            $psshSplit = substr($psshHex, $kidPos);
            $kidPotential = explode($kidSplitter, $psshSplit);
            
            foreach ($kidPotential as $kid) {
                if (strlen($kid) >= $kidLength) {
                    $kid = strtolower(substr($kid, 0, $kidLength));
                    if (!in_array($kid, $kidArray)) {
                        $kidArray[] = $kid;
                    }
                }
            }
        }
        
        return $kidArray;
    }

    public function GetPSSH($PSSH) {
        // Use regex to extract pssh value
        $pattern = '/<(?:cenc:pssh|pssh)\s*[^>]*>(.*?)<\/(?:cenc:pssh|pssh)>/s';
        preg_match_all($pattern, $PSSH, $matches);
        
        foreach ($matches[1] as $pssh) {
            if ($this->IsValidWidevinePSSH($pssh)) {
                return $pssh;
            }
        }
        
        return null;
    }

    private function ExtractKidFromManifest($Manifest) {
        $posDefault = strpos($Manifest, "default_KID");
        $posMarlin = strpos($Manifest, "marlin:kid");
        
        if ($posDefault !== false) {
            return $this->ExtractCencKid($Manifest);
        } elseif ($posMarlin !== false) {
            return $this->ExtractMarlinKid($Manifest);
        } else {
            return [];
        }
    }

    private function ExtractCencKid($Manifest) {
        $pattern = '/(?<=cenc:default_KID=")([^"]+)/';
        preg_match_all($pattern, $Manifest, $matches);
        $defaultKids = $matches[1];

        $kids = [];
        foreach ($defaultKids as $defaultKid) {
            $defaultKid = str_replace("-", "", $defaultKid);
            $kids[] = $defaultKid;
        }
        
        // Remove duplicate kids
        $kids = array_unique($kids);

        return $kids;
    }

    private function ExtractMarlinKid($Manifest) {
        $pattern = '/<mas:MarlinContentId>([^<]+)/';
        preg_match_all($pattern, $Manifest, $matches);

        $contentIds = $matches[1];
        $kids = [];
        foreach ($contentIds as $contentId) {
            $contentId = str_replace("urn:marlin:kid:", "", $contentId);
            $contentId = ltrim($contentId, ":");
            $kids[] = $contentId;
        }
        
        // Remove duplicate kids
        $kids = array_unique($kids);

        return $kids;
    }

    public function GetKID($Data) {
        $URL = $Data["URL"];
        $UseProxy = $Data["UseProxy"] == "true";
        $ProxyURL = $Data["ProxyURL"];
        $ProxyPort = $Data["ProxyPort"];
        $ProxyUser = $Data["ProxyUser"];
        $ProxyPass = $Data["ProxyPass"];

        $data = $this->GetURL($URL, $UseProxy, $ProxyURL, $ProxyPort, $ProxyUser, $ProxyPass);
        $pssh = $this->GetPSSH($data);
        $kidArray = [];

        // Extract kid from PSSH if available
        if ($pssh !== null) {
            $kidArray = $this->ExtractKidFromPSSH($pssh);
        }

        // Extract kid from manifest if PSSH doesn't contain any kid
        if (empty($kidArray)) {
            $kidArray = $this->ExtractKidFromManifest($data);
        }

        // If kid is still not found, try extracting from default_KID or marlin:kid
        if (empty($kidArray)) {
            $posDefault = strpos($data, "default_KID");
            $posMarlin = strpos($data, "marlin:kid");
            
            if ($posDefault !== false) {
                $kid = substr($data, $posDefault + 13, 36);
                $kid = str_replace("-", "", $kid);
                $kidArray[] = $kid;
            } elseif ($posMarlin !== false) {
                $kidStart = $posMarlin + 10;
                $kidEnd = strpos($data, "</mas:MarlinContentId>", $kidStart);
                $kid = substr($data, $kidStart, $kidEnd - $kidStart);
                $kid = str_replace("urn:marlin:kid:", "", $kid);
                $kid = ltrim($kid, ":");
                $kidArray[] = $kid;
            } else {
                return null; // Return null if neither "default_KID" nor "marlin:kid" is found
            }
        }

        return $kidArray;
    }

    public function GetUsers() {
        $query = "SELECT * FROM users";
        $statement = $this->DB->prepare($query);
        $statement->execute();
        
        // Fetch all users as associative arrays
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        return $users;
    }

    public function insertLine($username, $password, $expire_date) {
        try {
            $stmt = $this->db->prepare("INSERT INTO `lines` (`username`, `password`, `expire_date`) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password, $expire_date]);
            return true; // Return true if insertion is successful
        } catch (PDOException $e) {
            // Handle the exception (e.g., log error, return false)
            error_log('Error inserting line: ' . $e->getMessage());
            return false; // Return false if insertion fails
        }
    }
    
    public function generateRandomString($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        // Use a more secure random number generator
        if (function_exists('random_int')) {
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[random_int(0, $charactersLength - 1)];
            }
        } else {
            // Fallback to mt_rand if random_int is not available (PHP 7+)
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
            }
        }

        return $randomString;
    }


}