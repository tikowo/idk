<?php

namespace App\Http\Controllers\Users;

use App\Models\Genre;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Watchlater;
use App\Models\Movie;
use App\Helpers\Helpers;

class MoviesController extends Controller
{

    /**
     * This Constructer check if the user is make payment or not if not it will return 404
     *
     * MoviesController constructor.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            Helpers::checkUserPayment(Auth::user());
            return $next($request);
        });

    }

    /**
     * Get all movies
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllMovies()
    {
        $movieQuery = DB::select('
                      SELECT
                      movies.m_id AS id,
                      movies.m_name AS name,
                      movies.m_poster AS poster,
                      movies.m_desc AS overview,
                      movies.m_runtime AS runtime,
                      movies.m_year AS year,
                      movies.m_genre AS genre,
                      movies.m_rate AS rate,
                      movies.m_backdrop AS backdrop,
                      movies.m_age AS age,
                      u2.current_time,
                      u2.duration_time,
                      CASE
                      WHEN u1.id IS NULL THEN false
                      ELSE true
                      END AS "is_favorite",
                      CASE
                      WHEN u3.id IS NULL THEN false
                      ELSE true
                      END AS "is_like",
                      movies.m_cloud AS cloud
                      FROM movies
                      LEFT JOIN collection_lists  AS u1 ON u1.movie_id = movies.m_id AND u1.uid = "' .Auth::id(). '"
                      LEFT JOIN recently_watcheds AS u2 ON u2.movie_id = movies.m_id AND u2.uid = "' .Auth::id(). '"
                      LEFT JOIN likes AS u3 ON u3.movie_id = movies.m_id AND u3.uid = "' .Auth::id(). '"
                      WHERE movies.show <> 0 AND movies.m_age <> "G"
                      ORDER BY movies.created_at DESC
                      LIMIT 100');

        // Check if there is no movies
        if (empty($movieQuery)) {
            $movieQuery = null;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'movies' => $movieQuery
            ]]);
    }


    /**
     * Get movie details
     *
     * @param uuid $id
     * @return void
     */
    public function getMovieDetails($id)
    {

        //Check if moive already
        $check = Movie::find($id);

        if (is_null($check)) {
            return response()->json(['status' => 404], 404);
        }

        $movieQuery = DB::select('
                      SELECT DISTINCT
                      movies.m_id AS id,
                      movies.m_name AS name,
                      movies.m_poster AS poster,
                      movies.m_desc AS overview,
                      movies.m_runtime AS runtime,
                      movies.m_year AS year,
                      movies.m_genre AS genre,
                      movies.m_rate AS rate,
                      movies.m_backdrop AS backdrop,
                      movies.m_age AS age,
                      movies.m_youtube AS trailer,
                      u2.current_time,
                      u2.duration_time,
                      CASE
                      WHEN u1.id IS NULL THEN false
                      ELSE true
                      END AS "is_favorite",
                      CASE
                      WHEN u3.id IS NULL THEN false
                      ELSE true
                      END AS "is_like",
                      COUNT(u3.movie_id) AS likes_number,
                      movies.m_cloud AS cloud
                      FROM movies
                      LEFT JOIN collection_lists AS u1  ON u1.movie_id = movies.m_id AND u1.uid = "' .Auth::id(). '"
                      LEFT JOIN recently_watcheds AS u2 ON u2.movie_id = movies.m_id AND u2.uid = "' .Auth::id(). '"
                      LEFT JOIN likes AS u3  ON u3.movie_id = movies.m_id AND u3.uid = "' .Auth::id(). '"
                      WHERE movies.m_id = "'. $id .'" AND movies.show <> 0
                      Group By movies.m_id,u2.current_time,u2.duration_time,u1.id,u3.id
                      LIMIT 1');

        // Check if there is no movies
        if (empty($movieQuery)) {
            $movieQuery = null;
        }


        // Get casts
        $getMovieCast = DB::table('casts')
                    ->select('casts.c_id AS id', 'casts.c_name AS name', 'casts.c_image AS image')
                    ->join('casts_rules', 'casts_rules.casts_id', '=', 'casts.credit_id')
                    ->where('casts_movies', '=', $id)
                    ->get();

        // Check if there is no cast
        if ($getMovieCast->isEmpty()) {
            $getMovieCast = null;
        }
        // Get casts
        $getSimilarMovies = DB::table('movies')
                    ->selectRaw('m_id AS id, m_name AS name,m_poster AS poster, m_cloud AS cloud')
                    ->whereRaw('m_genre LIKE "'.strtok($movieQuery[0]->genre, "-").'%"')
                    ->whereRaw('m_id <> "'. $movieQuery[0]->id .'"')
                    ->limit(4)
                    ->get();

        // Check if there is no cast
        if ($getSimilarMovies->isEmpty()) {
            $getSimilarMovies = null;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'movie' => $movieQuery[0],
                'casts' => $getMovieCast,
                'similar' => $getSimilarMovies
            ]]);
    }



    /**
     * Sort by trending and genre
     *
     * @return \Illuminate\Http\Response
     */
    public function sortMovies(Request $request)
    {
        $request->validate([
            'trending' => 'required|numeric|between:1,5',
            'genre' => 'required|string|max:15'
        ]);

        if ($request->input('genre') === 'all') {
            $request->genre = "";
        }else{
            // Check if genre request equal the array genre
            $CheckGenere = Genre::where('name', $request->input('genre'))->first();
            if (is_null($CheckGenere)) {
                return response()->json(['status' => 'error', 'message' => 'Genre not found'], 400);
            }
        }

        if ($request->input('genre') === 'all') {
            $request->genre = "";
        }

        if ($request->input('trending') === 1) {
            $trending = 'movies.updated_at';
        } elseif ($request->input('trending') === 2) {
            $trending = 'm_year';
        } elseif ($request->input('trending') === 3) {
            $trending = 'm_rate';
        } elseif ($request->input('trending') === 4) {
            $trending = 'likes.movie_id';
        }
        $movieQuery = DB::select('
                      SELECT DISTINCT
                      movies.m_id AS id,
                      movies.m_name AS name,
                      movies.m_poster AS poster,
                      movies.m_desc AS overview,
                      movies.m_runtime AS runtime,
                      movies.m_year AS year,
                      movies.m_genre AS genre,
                      movies.m_rate AS rate,
                      movies.m_backdrop AS backdrop,
                      movies.m_age AS age,
                      u2.current_time,
                      u2.duration_time,
                      CASE
                      WHEN u1.id IS NULL THEN false
                      ELSE true
                      END AS "is_favorite",
                      CASE
                      WHEN u3.id IS NULL THEN false
                      ELSE true
                      END AS "is_like",
                      movies.m_cloud AS cloud
                      FROM movies
                      LEFT JOIN likes ON likes.movie_id = movies.m_id
                      LEFT JOIN collection_lists AS u1  ON u1.movie_id = movies.m_id AND u1.uid = "' .Auth::id(). '"
                      LEFT JOIN recently_watcheds AS u2 ON u2.movie_id = movies.m_id AND u2.uid = "' .Auth::id(). '"
                      LEFT JOIN likes AS u3  ON u3.movie_id = movies.m_id AND u3.uid = "' .Auth::id(). '"
                      WHERE movies.m_genre LIKE "'. $request->genre .'%" AND movies.show <> 0 AND movies.m_age <> "G"
                      ORDER BY ' .$trending. ' DESC
                      LIMIT 100');

        // Check if there is no movies
        if (empty($movieQuery)) {
            $movieQuery = null;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'movies' => $movieQuery
            ]]);
    }
}
