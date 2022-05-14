<?php

namespace app\libraries;

use app\entities\course\CourseMaterial;
use app\entities\course\CourseMaterialAccess;
use app\exceptions\MalformedDataException;
use app\models\User;

class CourseMaterialsUtils {
    /**
     * Determine if a course materials file has been released.
     *
     * @param CourseMaterial $course_material The course material to be checked
     * @return bool Indicates if the file has been released or not
     * @throws MalformedDataException An error occurred parsing the file's release time data
     */
    public static function isMaterialReleased(CourseMaterial $course_material): bool {
        if ($course_material == null) {
            return false;
        }
        $current_time = new \DateTime('now');
        $release_time = $course_material->getReleaseDate();

        return $current_time > $release_time;
    }

    /**
     * Determine if a course materials file can be viewed by the current user's section.
     *
     * @param CourseMaterial $course_material The course material to be checked
     * @param User $current_user the current user
     * @return bool Indicates if the file has been released or not
     */
    public static function isSectionAllowed(CourseMaterial $course_material, User $current_user): bool {
        if ($course_material == null) {
            return false;
        }
        return ($current_user->getGroup() < 4 || $course_material->isSectionAllowed($current_user->getRegistrationSection()));
    }

    /**
     * Check if the current user has permission to access a course materials file.
     *
     * @param string $path Absolute path to the file
     * @return string An empty string indicates that all checks passed and the user should have access to the file.
     *                A non-empty string will indicate what type of restriction should prevent the user from accessing
     *                the file.
     */
    public static function accessCourseMaterialCheck(Core $core, string $path): string {
        $course_material = $core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['path' => $path]);

        return self::finalAccessCourseMaterialCheck($core, $course_material);
    }

    public static function finalAccessCourseMaterialCheck(Core $core, CourseMaterial $course_material) {
        if (!CourseMaterialsUtils::isMaterialReleased($course_material)) {
            return 'You may not access this file until it is released.';
        }

        if (!CourseMaterialsUtils::isSectionAllowed($course_material, $core->getUser())) {
            return 'Your section may not access this file.';
        }

        return '';
    }

    public static function insertCourseMaterialAccess(Core $core, string $path) {
        $course_material = $core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['path' => $path]);
        $course_material_access = new CourseMaterialAccess(
            $course_material,
            $core->getUser()->getId(),
            DateUtils::getDateTimeNow()
        );
        $course_material->addAccess($course_material_access);
        $core->getCourseEntityManager()->persist($course_material_access);
        $core->getCourseEntityManager()->flush();
    }
}
