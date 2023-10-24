import { Anchor, Breadcrumbs } from '@mantine/core';
import React, { Suspense } from 'react';
import CourseLink from '../heading/CourseLink';
import Link from 'next/link';
import googleClassroom from '@/lib/config/googleClassroom';
import { classroom_v1 } from 'googleapis';

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

const getSubmissions = async (courseId: string, courseWorkId: string) => {
  const classroom = await googleClassroom();
  const { data } = await classroom.courses.courseWork.studentSubmissions.list({
    courseId,
    courseWorkId,
    states: ['CREATED', 'TURNED_IN', 'RETURNED'],
  });
  return data.studentSubmissions;
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
  const courseWork = await getCourseWork(courseId, courseWorkId);

  const submissions = await getSubmissions(courseId, courseWorkId);
  const students = await getStudents(courseId);

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
      {students?.map((it) => (
        <h1 key={it.userId}>{it.profile?.name?.fullName}</h1>
      ))}
    </>
  );
}
