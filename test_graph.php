<?php
function a() { b(); c(); }
function b() { d(); }
function c() { d(); }
function d() { /* do nothing */ }
a();
