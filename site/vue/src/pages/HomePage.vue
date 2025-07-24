<script setup lang="ts">
import { defineProps } from 'vue';
import { buildUrl } from '../../../ts/utils/server';
import AllNotificationsDisplay from '@/components/AllNotificationsDisplay.vue';

interface Notification {
    id: number;
    component: string;
    metadata: string;
    content: string;
    seen: boolean;
    elapsed_time: number;
    created_at: string;
    notify_time: string;
    semester: string;
    course: string;
    notification_url: string;
}

type Status = 'unarchived_courses' | 'dropped_courses' | 'self_registration_courses' | 'archived_courses';
type Rank = {
    title: string;
    courses: Course[];
};
type Course = {
    semester: string;
    title: string;
    display_semester: string;
    display_name?: string;
    registration_section?: string;
};

interface Props {
    statuses: { [key in Status]: { [key: string]: Rank } };
    notifications: Notification[];
}

type SemesterCourses = {
    [semester: string]: Course[];
};

defineProps<Props>();

const getCourseTypeHeader = (course_type: Status) => {
    if (course_type === 'self_registration_courses') {
        return 'Available for Self Registration';
    }
    let message = 'Active';
    if (course_type === 'dropped_courses') {
        message = 'Recently Dropped ';
    }
    else if (course_type === 'archived_courses') {
        message = 'Archived ';
    }
    return `My ${message} Courses`;
};

const groupCoursesBySemester = (courses: Course[]) => {
    if (!courses) {
        return {};
    }
    return courses.reduce((acc: SemesterCourses, course) => {
        const semester = course.display_semester;
        if (!acc[semester]) {
            acc[semester] = [];
        }
        acc[semester].push(course);
        return acc;
    }, {});
};

const buildCourseUrl = (course: Course) => {
    return buildUrl(['courses', course.semester, course.title]);
};
</script>

<template>
  <div
    class="home-content grid-container"
  >
    <div
      id="courses"
      class="div1 shadow"
      data-testid="courses-list"
    >
      <template
        v-for="(ranks, course_type, index) in statuses"
        :key="course_type"
      >
        <div v-if="index === 0 || (ranks && Object.keys(ranks).length > 0)">
          <br v-if="index > 0" />
          <br v-if="index > 0" />

          <h1
            class="courses-header"
            data-testid="courses-header"
          >
            {{ getCourseTypeHeader(course_type) }}
          </h1>

          <div
            v-for="rank in ranks"
            :key="rank.title"
          >
            <h3 v-if="course_type !== 'dropped_courses' && course_type !== 'self_registration_courses'">
              As {{ rank.title }}
            </h3>

            <div
              v-for="(courses, semester) in groupCoursesBySemester(rank.courses)"
              :key="semester"
            >
              <ul class="bare-list course-list">
                <li
                  v-for="course in courses"
                  :key="`${course.semester}_${course.title}`"
                >
                  <a
                    :id="`${course.semester}_${course.title}`"
                    class="btn btn-primary btn-block btn-course"
                    :href="buildCourseUrl(course)"
                    :data-testid="`${course.title}-button`"
                  >
                    {{ course.display_semester }} &nbsp; &nbsp;
                    {{ course.title.toUpperCase() }} &nbsp; &nbsp;
                    <template v-if="course.display_name">
                      {{ course.display_name }} &nbsp; &nbsp;
                    </template>
                    <template v-if="course.registration_section !== null">
                      (Section {{ course.registration_section }})
                    </template>
                  </a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </template>
    </div>
    <AllNotificationsDisplay :notifications="notifications" />
  </div>
</template>

<style scoped>
.home-content {
    padding: 20px;
    margin: 10px;
}

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    grid-auto-rows: auto;
    align-items: start;
    grid-gap: 30px;
}

.courses-header {
    margin-bottom: 5px !important; /* Override submitty-vue.css */
}
.div1 {
  grid-column: 1;
  padding: 20px;
  background-color: var(--default-white);
}
.course-list li {
    margin: 3px 0;
}
ul.bare-list,
ol.bare-list {
    list-style: none;
    padding: 0;
}
.btn-course {
    text-align: left;
    padding: 6px 10px;
    white-space: normal;
    word-wrap: break-word;
}
#courses h2 {
    margin: 0;
}
</style>
