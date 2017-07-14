<?php

namespace Laravel\Horizon\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;

class FailedJobsController extends Controller
{
    /**
     * The job repository implementation.
     *
     * @var JobRepository
     */
    public $jobs;

    /**
     * The tag repository implementation.
     *
     * @var TagRepository
     */
    public $tags;

    /**
     * Create a new controller instance.
     *
     * @param  JobRepository $jobs
     * @param  TagRepository $tags
     * @return void
     */
    public function __construct(JobRepository $jobs, TagRepository $tags)
    {
        parent::__construct();

        $this->jobs = $jobs;
        $this->tags = $tags;
    }

    /**
     * Get all of the failed jobs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $jobs = ! $request->query('tag')
                ? $this->paginate($request)
                : $this->paginateByTag($request, $request->query('tag'));

        $total = $request->query('tag')
                ? $this->tags->count('failed:'.$request->query('tag'))
                : $this->jobs->countFailed();

        return [
            'jobs' => $jobs,
            'total' => $total,
        ];
    }

    /**
     * Paginate the failed jobs for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function paginate(Request $request)
    {
        return $this->jobs->getFailed($request->query('starting_at', -1))->map(function ($job) {
            return $this->decode($job);
        });
    }

    /**
     * Paginate the failed jobs for the request and tag.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $tag
     * @return array
     */
    protected function paginateByTag(Request $request, $tag)
    {
        $jobIds = $this->tags->paginate(
            'failed:'.$tag, $request->query('starting_at', -1) + 1, 50
        );

        $startingAt = $request->query('starting_at', 0);

        return $this->jobs->getJobs($jobIds, $startingAt)->map(function ($job) {
            return $this->decode($job);
        });
    }

    /**
     * Get a failed job instance.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return (array) $this->jobs->getJobs([$id])->map(function ($job) {
            return $this->decode($job);
        })->first();
    }

    /**
     * Decode the given job.
     *
     * @param  object  $job
     * @return object
     */
    protected function decode($job)
    {
        $job->payload = json_decode($job->payload);
        $job->retried_by = json_decode($job->retried_by);

        return $job;
    }
}