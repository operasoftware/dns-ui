<?php

function get_tag_comment($login)
{
    $shortname = explode('.', gethostname());
    $tag = date("d.m.Y H:i:s")." ".$shortname[0]." \"WEB\" ".$login.": ";
    return $tag;
}
