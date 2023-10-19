import { Anchor, Breadcrumbs, Text } from '@mantine/core';
import React, { Suspense } from 'react';
import CourseLink from '../heading/CourseLink';
import Link from 'next/link';
import googleClassroom from '@/lib/config/googleClassroom';

type Props = {
  params: {
    courseId: string;
    courseWorkId: string;
  };
};

const getCourseWork = async (courseId: string, courseWorkId: string) => {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.get({
    courseId,
    id: courseWorkId,
  });
  return courseWork;
};

export default async function SubmissionsPage({
  params: { courseId, courseWorkId },
}: Props) {
  const courseWork = await getCourseWork(courseId, courseWorkId);
  const items = [
    <Anchor component={Link} href={'/courses'} key={1}>
      Courses
    </Anchor>,
    <Suspense fallback={<div>Course</div>} key={2}>
      <CourseLink courseId={courseId} />
    </Suspense>,
    <Anchor
      component={Link}
      href={`/courses/${courseId}/work/${courseWorkId}`}
      key={3}
    >
      {courseWork.title}
    </Anchor>,
  ];
  return (
    <>
      <Breadcrumbs>{items}</Breadcrumbs>
    </>
  );
}
