<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();


/**	Define allowed actions of editlist */
class Modes
{
	const FIX = 0x00; /// read only list
	const MOD = 0x01; /// allows row modification only
	const DEL = 0x02; /// allows delete row
	const DUP = 0x04; /// allows creation of new record via copy of existant
	const ADD = 0x08; /// allows creation of new record from scratch
	const DDD = self::MOD | self::DEL | self::DUP; /// eDit, Duplicate and Delete
	const MDA = self::MOD | self::DEL | self::ADD; /// Edit, Delete and Add
	const DDA = self::DUP | self::DEL | self::ADD; /// Add, Delete and Duplicate
	const ALL = 0x0F; /// Allows all modification, equivalent to MOD | ADD | DEL | DUP
}
