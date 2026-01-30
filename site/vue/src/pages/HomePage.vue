<script setup lang="ts">
import { defineProps, ref, computed, onMounted } from 'vue';
import { buildUrl } from '../../../ts/utils/server';
import NotificationsDisplay from '@/components/NotificationsDisplay.vue';
import type { Notification } from '@/types/Notification';

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
    unseenCount: number;
    course: boolean;
    userId: string;
}

type SemesterCourses = {
    [semester: string]: Course[];
};

const props = defineProps<Props>();
const archivedCoursesVisible = ref(true);
const hasArchivedCourses = computed(() => {
    const archivedRanks = props.statuses.archived_courses;

    return archivedRanks && Object.keys(archivedRanks).length > 0
        && Object.values(archivedRanks).some((rank: Rank) => rank.courses && rank.courses.length > 0);
});
const toggleArchivedCourses = () => {
    archivedCoursesVisible.value = !archivedCoursesVisible.value;
    // Toggle archived course visibility indefinitely, scoped to user
    localStorage.setItem(`archived_courses_visible_${props.userId}`, archivedCoursesVisible.value.toString());
};

onMounted(() => {
    const storedValue = localStorage.getItem(`archived_courses_visible_${props.userId}`);

    // Default to visible if no value is stored
    if (storedValue !== null) {
        archivedCoursesVisible.value = storedValue === 'true';
    }
});

const getCourseTypeHeader = (course_type: Status) => {
    if (course_type === 'self_registration_courses') {
        return 'Courses Available for Self Registration';
    }
    let message = '';
    if (course_type === 'dropped_courses') {
        message = 'Recently Dropped ';
    }
    else if (course_type === 'archived_courses') {
        message = 'Archived ';
    }
    return `My ${message}Courses`;
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
          <div
            class="courses-header-container"
          >
            <h1
              class="courses-header"
              data-testid="courses-header"
            >
              {{ getCourseTypeHeader(course_type) }}
            </h1>
            <button
              v-if="course_type === 'archived_courses' && hasArchivedCourses"
              type="button"
              class="btn btn-default archive-toggle-btn"
              @click="toggleArchivedCourses"
            >
              {{ archivedCoursesVisible ? 'Hide' : 'Show' }}
            </button>
          </div>
          <div
            v-for="rank in ranks"
            v-show="course_type !== 'archived_courses' || archivedCoursesVisible"
            :key="rank.title"
          >
            <h2 v-if="course_type !== 'dropped_courses' && course_type !== 'self_registration_courses'" class="courses-rank-title">
              As {{ rank.title }}
            </h2>

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
              <br v-if="course_type === 'archived_courses'" />
            </div>
          </div>
        </div>
      </template>
    </div>
    <div
      class="notification-panel shadow"
    >
      <NotificationsDisplay
        :notifications="notifications"
        :unseenCount="unseenCount"
        :course="false"
      />
    </div>
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

@media (max-width: 540px) {
    .home-content {
        padding: 0px
    }
    .grid-container {
        grid-gap: 15px;
    }
}

.courses-header-container {
    display: flex;
    flex-direction: row;
    align-items: center;
}

.courses-header {
    margin-bottom: 5px !important; /* Override submitty-vue.css */
    flex-grow: 1;
}

.courses-rank-title {
    font-size: 19px;
}

.archive-toggle-btn {
    flex-grow: 0;
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
    overflow-wrap: break-word;
}
#courses h2 {
    margin: 0;
}

.notification-panel {
    background-color: var(--default-white);
    height: auto;
    padding: 20px;
}
</style>
