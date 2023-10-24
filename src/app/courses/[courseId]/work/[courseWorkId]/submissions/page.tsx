import { Anchor, Breadcrumbs, Center } from '@mantine/core';
import React, { Suspense } from 'react';
import CourseLink from '../heading/CourseLink';
import Link from 'next/link';
import googleClassroom from '@/lib/config/googleClassroom';
import { classroom_v1 } from 'googleapis';
import CourseWorkBreadcrumbs from '../heading/CourseWorkBreadcrumbs';

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

const getStudents = async (courseId: string) => {
  const classroom = await googleClassroom();
  const { data } = await classroom.courses.students.list({
    courseId,
  });
  return data.students;
};

export default async function SubmissionsPage({
  params: { courseId, courseWorkId },
}: Props) {
  // const courseWork = await getCourseWork(courseId, courseWorkId);

  // const students = await getStudents(courseId);

  return (
    <>
      <CourseWorkBreadcrumbs courseId={courseId} courseWorkId={courseWorkId} />

      <Center>Hello World</Center>
    </>
  );
}
