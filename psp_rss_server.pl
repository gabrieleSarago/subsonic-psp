#!/usr/bin/perl -w
# PSP RSS Server - Videos/MP3 separati + cartelle distinte
# /videos/*.mp4 | /music/*.mp3

use strict;
use HTTP::Daemon;
use HTTP::Status;
use XML::RSS;
use URI::Escape;
use Encode;
use LWP::MediaTypes;
use File::Basename qw(basename);
use File::stat;
use Time::localtime qw(ctime);
use Fcntl qw(SEEK_SET);  # Aggiungi all'inizio

# Config
my $feedtitle = "PSP Media Server";
my $hostname = $ENV{HOSTNAME};
my $port = 9000;
my $debug = 1;
my $rootaddr = "http://" . $hostname . ":" . $port;

# Cartelle
my $videos_dir = "/videos";
my $music_dir = "/music";

LWP::MediaTypes::add_type("video/mp4" => qw(mp4 MP4));
LWP::MediaTypes::add_type("audio/mpeg" => qw(mp3 MP3));

my $d = HTTP::Daemon->new(LocalPort => $port, LocalAddr => "0.0.0.0", Timeout => 30) 
  || die "Porta $port occupata\n";
print "Server: " . $d->url . " | Videos: $videos_dir | Music: $music_dir\n";

while (my $c = $d->accept) {
  my $pid = fork // next;  # Fork per gestire connessioni concorrenti
  exit 0 if $pid == 0;
  while (my $r = $c->get_request) {
    last unless $r;  # Esci se richiesta nulla
    my $path = uri_unescape($r->url->path);
    print $r->method, " ", $path, "\n" if $debug;

    if ($r->method eq 'GET') {
      if ($path eq '/') { send_index($c); }
      elsif ($path eq '/index.rss') { send_rss_combined($c); }
      elsif ($path eq '/video.rss') { send_rss_video($c); }
      elsif ($path eq '/audio.rss') { send_rss_audio($c); }
      elsif (serve_file($c, $path, $r)) { }
      else { $c->send_error(RC_NOT_FOUND); }
    } else {
      $c->send_error(RC_METHOD_NOT_ALLOWED);
    }
  }
  $c->close;
  exit 0 if $pid == 0;
}

sub send_index {
  my $c = shift;
  my $index = <<'EOF';
<!DOCTYPE html>
<html><head><title>PSP Media Server</title></head>
<body>
<h1>PSP Media Feed</h1>
<ul>
<li><a href="index.rss">Tutti (Videos+Music)</a></li>
<li><a href="video.rss">Videos (/videos/*.mp4)</a></li>
<li><a href="audio.rss">Music (/music/*.mp3)</a></li>
</ul>
</body></html>
EOF
  my $res = HTTP::Response->new(RC_OK);
  $res->header('Content-Type' => 'text/html');
  $res->header('Cache-Control' => 'no-cache');
  $res->header('Connection' => 'close');
  $res->header('Keep-Alive' => 'timeout=5');
  $res->content($index);
  $c->send_response($res);
}

sub send_rss_combined {
  my $c = shift;
  my ($vc, $mc) = count_media();
  my $rss = XML::RSS->new(version => '2.0');
  $rss->channel(title => "$feedtitle - Tutti", link => $rootaddr, description => "$vc videos + $mc music");
  add_rss_items($rss);
  send_rss_response($c, $rss);
}

sub send_rss_video {
  my $c = shift;
  my ($vc) = count_media();
  my $rss = XML::RSS->new(version => '2.0');
  $rss->channel(title => "$feedtitle - Videos", link => $rootaddr, description => "$vc MP4 in /videos");
  add_rss_items($rss, 'video');
  send_rss_response($c, $rss);
}

sub send_rss_audio {
  my $c = shift;
  my ($mc) = count_media();
  my $rss = XML::RSS->new(version => '2.0');
  $rss->channel(title => "$feedtitle - Music", link => $rootaddr, description => "$mc MP3 in /music");
  add_rss_items($rss, 'audio');
  send_rss_response($c, $rss);
}

sub count_media {
  my $vc = 0; my @mp4s = glob("$videos_dir/*.[mM][pP][4M]*");
  foreach (@mp4s) { $vc++ if get_mp4tag($_); }
  my $mc = 0; my @mp3s = glob("$music_dir/*.[mM][pP][3M]*");
  foreach (@mp3s) { $mc++ if get_mp3tag($_); }
  return ($vc, $mc);
}

sub add_rss_items {
  my ($rss, $type) = @_;
  $type ||= 'combined';

  if ($type eq 'combined' || $type eq 'video') {
    my @mp4s = glob("$videos_dir/*.mp[4M]*");
    foreach my $file (@mp4s) {
      my $tag = get_mp4tag($file) || next;
      my $date = ctime(stat($file)->mtime);
      my $escaped = uri_escape($tag->{NAM}, "^A-Za-z0-9\-\._~");
      $rss->add_item(
        title => $tag->{NAM},
        description => "Video: " . $tag->{NAM},
        pubDate => $date,
        enclosure => { url => "$rootaddr/" . $escaped . ".mp4", type => 'video/mp4' }
      );
    }
  }

  if ($type eq 'combined' || $type eq 'audio') {
    my @mp3s = glob("$music_dir/*.mp[3M]*");
    foreach my $file (@mp3s) {
      my $tag = get_mp3tag($file) || next;
      my $date = ctime(stat($file)->mtime);
      my $escaped = uri_escape($tag->{TITLE}, "^A-Za-z0-9\-\._~");
      $rss->add_item(
        title => $tag->{TITLE} . ($tag->{ARTIST} ? " - " . $tag->{ARTIST} : ""),
        description => "Music: " . $tag->{TITLE},
        pubDate => $date,
        author => $tag->{ARTIST} || 'Unknown Artist',
        enclosure => { url => "$rootaddr/" . $escaped . ".mp3", type => 'audio/mpeg' }
      );
    }
  }
}

sub send_rss_response {
  my ($c, $rss) = @_;
  my $res = HTTP::Response->new(RC_OK);
  $res->header('Content-Type' => 'application/rss+xml');
  $res->header('Cache-Control' => 'no-cache');
  $res->header('Connection' => 'close');
  $res->header('Keep-Alive' => 'timeout=5');
  $res->content($rss->as_string);
  $c->send_response($res);
  print "RSS '$rss->{channel}{title}' inviato\n" if $debug;
}

sub serve_file {
  my ($c, $path, $r) = @_;
  $path = substr($path, 1);
  my ($title, $ext) = ($path =~ m|^(.+)\.(.+?)$|);

  return 0 unless $ext && ($ext =~ /^mp4$/i || $ext =~ /^mp3$/i);
  $title = uri_unescape($title);

  my $dir = $ext =~ /^mp4$/i ? $videos_dir : $music_dir;
  my @candidates = glob("$dir/*.[mM][pP][43]");  # Flex per case
  foreach my $file (@candidates) {
    my $tag = $ext =~ /^mp4$/i ? get_mp4tag($file) : get_mp3tag($file);
    next unless $tag;
    my $file_title = $tag->{NAM} || $tag->{TITLE} || basename($file, ".$ext");
    $file_title = lc($file_title);  # Normalizza minuscolo
    my $req_title = lc($title);
    if ($file_title eq $req_title) {
      return send_partial_file($c, $file, $ext, $r);
    }
  }
  return 0;
}

sub send_partial_file {
  my ($c, $file, $ext, $r) = @_;  # $r aggiunto
  my $size = -s $file;
  return 0 unless $size;

  my $res = HTTP::Response->new(RC_OK);
  my $type = $ext =~ /^mp4$/i ? 'video/mp4' : 'audio/mpeg';
  $res->header('Accept-Ranges' => 'bytes');
  $res->header('Content-Type' => $type);
  $res->header('Content-Length' => $size);

  # Range da REQUEST corrente
  my $range = $r->header('Range');
  print "Range header: '$range'\n" if $debug;

  if ($range && $range =~ /bytes=(\d+)-(\d*)/) {
    my ($start, $end) = ($1, $2 || $size - 1);
    $end = $size - 1 if $end >= $size;
    my $clen = $end - $start + 1;

    $res->code(206);  # RC_PARTIAL_CONTENT = 206
    $res->header('Content-Range' => "bytes $start-$end/$size");
    $res->header('Content-Length' => $clen);

    open my $fh, '<:raw', $file or return 0;  # Raw per binary
    seek($fh, $start, SEEK_SET);
    
    $c->send_response($res);  # Invia headers
    my $buf;
    while ($clen > 0 && read($fh, $buf, 65536)) {  # Chunk 64KB
      $c->syswrite($buf, length($buf));
      $clen -= length($buf);
    }
    close $fh;
  } else {
    # Full file fallback
    $c->send_file_response($file);
  }

  print "Streaming $ext [$size bytes]: $file " . ($range ? "(range)" : "(full)") . "\n" if $debug;
  return 1;
}

sub get_mp4tag {
  my $file = shift;
  eval { require MP4::Info; MP4::Info->import; 1 };
  if ($@) { return { NAM => basename($file, qw(.mp4 .MP4)) }; }
  my $tag = MP4::Info::get_mp4tag($file);
  return $tag ? { NAM => $tag->{NAM} || basename($file, qw(.mp4 .MP4)) } : undef;
}

sub get_mp3tag {
  my $file = shift;
  eval { require MP3::Info; MP3::Info->import; 1 };
  if ($@) { return { TITLE => basename($file, qw(.mp3 .MP3)) }; }
  my $tag = MP3::Info::get_mp3tag($file);
  return $tag ? {
    TITLE => $tag->{TITLE} || basename($file, qw(.mp3 .MP3)),
    ARTIST => $tag->{ARTIST} || ''
  } : undef;
}
