<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Durée de conservation de la corbeille
    |--------------------------------------------------------------------------
    |
    | Nombre de jours pendant lesquels un compte ou un matériel supprimé
    | logiquement reste restaurable avant sa suppression définitive
    | (commande planifiée `corbeille:purger`). Un an par défaut.
    |
    */

    'retention_days' => (int) env('CORBEILLE_RETENTION_DAYS', 365),

];
