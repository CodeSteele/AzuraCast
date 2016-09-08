<?php
namespace Modules\Stations\Controllers;

use Entity\Station;
use Entity\StationPlaylist;

class PlaylistsController extends BaseController
{
    public function indexAction()
    {
        $all_playlists = $this->station->playlists;

        $total_weights = 0;
        foreach($all_playlists as $playlist)
            $total_weights += $playlist->weight;

        $playlists = array();
        foreach($all_playlists as $playlist)
        {
            $playlist_row = $playlist->toArray();
            $playlist_row['probability'] = round(($playlist->weight / $total_weights) * 100, 1).'%';
            $playlist_row['num_songs'] = count($playlist->media);

            $playlists[$playlist->id] = $playlist_row;
        }

        $this->view->playlists = $playlists;
    }

    public function editAction()
    {
        $form_config = $this->current_module_config->forms->playlist;
        $form = new \App\Form($form_config);

        if ($this->hasParam('id'))
        {
            $record = StationPlaylist::getRepository()->findOneBy(array(
                'id' => $this->getParam('id'),
                'station_id' => $this->station->id
            ));
            $form->setDefaults($record->toArray());
        }

        if(!empty($_POST) && $form->isValid($_POST))
        {
            $data = $form->getValues();

            if (!($record instanceof StationPlaylist))
            {
                $record = new StationPlaylist;
                $record->station = $this->station;

                // Always force a reload for new stations.
                $reload_station = true;
            }
            else
            {
                // Only reload for a rename.
                $reload_station = (strcmp($record->name, $data['name']) !== 0);
            }

            $record->fromArray($data);
            $record->save();

            if ($reload_station)
                $this->_reloadStation();

            $this->alert('<b>Stream updated!</b>', 'green');

            return $this->redirectFromHere(['action' => 'index', 'id' => NULL]);
        }

        $title = (($this->hasParam('id')) ? 'Edit' : 'Add').' Playlist';
        return $this->renderForm($form, 'edit', $title);
    }

    public function deleteAction()
    {
        $id = (int)$this->getParam('id');

        $record = StationPlaylist::getRepository()->findOneBy(array(
            'id' => $id,
            'station_id' => $this->station->id
        ));

        if ($record instanceof StationPlaylist)
            $record->delete();

        $this->_reloadStation();

        $this->alert('<b>Record deleted.</b>', 'green');
        return $this->redirectFromHere(['action' => 'index', 'id' => NULL]);
    }

    protected function _reloadStation()
    {
        $backend = $this->station->getBackendAdapter();

        $backend->stop();
        $backend->write();
        $backend->start();

        $this->alert('<b>Radio backend reloaded.</b><br>Your listeners will not be disconnected by playlist changes, but songs may change abruptly.', 'green');
    }
}