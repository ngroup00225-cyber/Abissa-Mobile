<?php
function abissaDeleteAccount(string $userId): void {
    global $dataDir, $uploadDir;
    $managed=['users','state','submissions','user_sessions','learning_progress','member_registry','credential_prints','credential_registry','qrcodes','directory_meta','proof_files','proof_upload_attempts','verification_codes','verification_tokens','blog_posts','comment_likes','post_likes','comment_reports','feedback_messages','community_notifications','community_limits','user_blocks','push_devices','push_deliveries'];
    $records=[];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){
        if(!$file->isFile()||$file->isLink()||$file->getExtension()!=='json')continue;
        if(!in_array($file->getBasename('.json'),$managed,true))continue;
        $relative=substr($file->getPathname(),strlen($dataDir)+1);
        if(str_contains($relative,DIRECTORY_SEPARATOR)&&!str_starts_with($relative,'backups'.DIRECTORY_SEPARATOR))continue;
        $raw=(string)file_get_contents($file->getPathname());
        $value=json_decode(preg_replace('/^\xEF\xBB\xBF/','',$raw),true,512,JSON_THROW_ON_ERROR);
        $records[$file->getPathname()]=['name'=>$file->getBasename('.json'),'value'=>$value];
    }
    $ids=[$userId=>true,'user-'.$userId=>true];
    foreach($records as $record){if($record['name']==='blog_posts')foreach($record['value'] as $p)if(($p['userId']??'')===$userId&&!empty($p['id']))$ids[(string)$p['id']]=true;if($record['name']==='state')foreach($record['value']['COMMENTS']??[] as $c)if(($c['userId']??'')===$userId&&!empty($c['id']))$ids[(string)$c['id']]=true;}$uploads=[];$proofs=[];$phones=[];
    $collectMedia=function($node)use(&$collectMedia,&$uploads){
        if(is_array($node)){foreach($node as $child)$collectMedia($child);return;}
        if(!is_string($node))return;
        $path=parse_url($node,PHP_URL_PATH);
        if(!is_string($path)||!preg_match('~(?:^|/)uploads/((?:public-)?[a-f0-9]{32}\.(?:png|jpg|jpeg|webp|gif))$~D',$path,$m))return;
        if(str_contains($node,'://')&&parse_url($node,PHP_URL_HOST)!=='abissamobile.com')return;
        $uploads[$m[1]]=true;
    };
    foreach($records as $record){
        if($record['name']==='submissions')foreach((array)$record['value'] as $row)if(is_array($row)&&($row['userId']??'')===$userId){if(!empty($row['id']))$ids[(string)$row['id']]=true;$collectMedia($row);}
        if($record['name']==='users')foreach((array)$record['value'] as $row)if(is_array($row)&&($row['id']??'')===$userId){$collectMedia($row);$phone=normalizePhone((string)($row['phone']??''));if($phone!=='')$phones[$phone]=true;}
        if($record['name']==='proof_files')foreach((array)$record['value'] as $id=>$row)if(is_array($row)&&($row['ownerId']??'')===$userId&&preg_match('/^[a-f0-9]{32}$/D',(string)$id)&&preg_match('/^(pdf|png|jpg|webp)$/D',(string)($row['ext']??'')))$proofs[$id.'.'.$row['ext']]=true;
    }
    $belongs=function(array $node)use($userId,&$ids):bool{
        if(($node['userId']??'')===$userId||($node['ownerId']??'')===$userId)return true;
        foreach(['id','sourceId','submissionId','documentId','feedbackId','commentId'] as $field)if(isset($node[$field])&&is_string($node[$field])&&isset($ids[$node[$field]]))return true;
        return false;
    };
    $purge=function($node)use(&$purge,$belongs,&$ids){
        if(!is_array($node))return $node;
        $list=array_is_list($node);$clean=[];
        foreach($node as $key=>$child){
            if(is_string($key)&&isset($ids[$key]))continue;
            if(is_array($child)&&$belongs($child))continue;
            if($list&&is_string($child)&&isset($ids[$child]))continue;
            if($list)$clean[]=$purge($child);else $clean[$key]=$purge($child);
        }
        return $clean;
    };
    $changes=[];$survivors='';
    foreach($records as $path=>$record){
        $value=$record['value'];
        if(in_array($record['name'],['verification_codes','verification_tokens'],true))$value=array_filter((array)$value,static fn($row)=>!is_array($row)||!isset($phones[normalizePhone((string)($row['phone']??''))]));
        $clean=$purge($value);$encoded=json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $survivors.=$encoded;
        if($clean!==$record['value'])$changes[$path]=$encoded;
    }
    // Logs are filtered by explicit ownership; unrelated operational records stay intact.
    foreach(['activity_log','security_log','print_history'] as $name){
        $path=$dataDir.'/'.$name.'.jsonl';if(!is_file($path))continue;$lines=[];$changed=false;
        foreach(file($path,FILE_IGNORE_NEW_LINES) as $line){
            if(trim($line)==='')continue;
            $row=json_decode($line,true,512,JSON_THROW_ON_ERROR);
            if(is_array($row)&&($belongs($row)||$purge([$row])!==[$row])){$changed=true;continue;}
            $lines[]=$line;
        }
        if($changed)$changes[$path]=implode("\n",$lines).($lines?"\n":'');
    }
    // Stage every update before modifying storage. Remove the live user record last,
    // so a filesystem failure can be retried by the owner using the same endpoint.
    $staged=[];
    try{
        foreach($changes as $path=>$encoded){
            $tmp=$path.'.'.bin2hex(random_bytes(8)).'.delete.tmp';
            if(file_put_contents($tmp,$encoded,LOCK_EX)===false)throw new RuntimeException('Write failed');
            chmod($tmp,0640);$staged[$path]=$tmp;
        }
        foreach($staged as $path=>$tmp)if($path!==$dataDir.'/users.json'&&$path!==$dataDir.'/user_sessions.json'){
            if(!rename($tmp,$path))throw new RuntimeException('Replace failed');unset($staged[$path]);
        }
        foreach($uploads as $name=>$_){if(str_contains($survivors,$name))continue;$path=$uploadDir.'/'.$name;if(is_file($path)&&!is_link($path)&&!unlink($path))throw new RuntimeException('Photo delete failed');}
        foreach($proofs as $name=>$_){$path=$dataDir.'/proofs/'.$name;if(is_file($path)&&!is_link($path)&&!unlink($path))throw new RuntimeException('Proof delete failed');}
        foreach([$dataDir.'/users.json',$dataDir.'/user_sessions.json'] as $path)if(isset($staged[$path])){if(!rename($staged[$path],$path))throw new RuntimeException('Final replace failed');unset($staged[$path]);}
    }finally{foreach($staged as $tmp)if(is_file($tmp))unlink($tmp);}
}
