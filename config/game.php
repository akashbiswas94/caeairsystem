<?php

return [
    // Change these paths if Unreal Engine or the project is installed elsewhere.
    'unreal_editor_candidates' => [
        'C:\\Program Files\\Epic Games\\UE_5.6\\Engine\\Binaries\\Win64\\UnrealEditor.exe',
        'D:\\Program Files\\Epic Games\\UE_5.6\\Engine\\Binaries\\Win64\\UnrealEditor.exe',
    ],

    'project_candidates' => [
        'D:\\QUT\\IFN636\\CAE_Project\\Development\\projectboomboom\\UE5 5.6 5.6\\projectboomboom.uproject',
        'D:\\QUT\\IFN636\\CAE_Project\\Development\\projectboomboom\\UE5 5.6\\projectboomboom.uproject',
    ],

    'map' => '/Game/BoomBoom/Levels/Main_World',

    // Runs the project directly as a standalone game window rather than opening PIE.
    'arguments' => [
        '-game',
        '-windowed',
        '-ResX=1280',
        '-ResY=720',
        '-log',
    ],
];
