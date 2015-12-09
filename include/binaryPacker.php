<?php

class binaryPacker {
    static function packInt($val) {
        $buf = pack('L', (int)$val);
        return $buf;
    }

    static function unpackInt($buf) {
        $unpackedVal = unpack('L', $buf);
        $unpackedVal = $unpackedVal[1];
        if ($unpackedVal <0) {
            $unpackedVal += 4294967296;
        }
        return (float)$unpackedVal;
    }
};