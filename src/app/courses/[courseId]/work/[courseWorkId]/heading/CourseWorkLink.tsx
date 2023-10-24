import googleClassroom from '@/lib/config/googleClassroom';
import { Anchor } from '@mantine/core';
import Link from 'next/link';
import React from 'react';

type Props = {
  courseId?: string | null;
  courseWorkId?: string | null;
};

const getCourseWorkName = async (
  courseId?: string | null,
  courseWorkId?: string | null
) => {
  const classroom = await googleClassroom();
  if (!courseId || !courseWorkId) return null;
  const { data: courseWork } = await classroom.courses.courseWork.get({
    courseId,
    id: courseWorkId,
  });
  return courseWork.title;
};

export default async function CourseWorkLink({
  courseId,
  courseWorkId,
}: Props) {
  const courseWorkName = await getCourseWorkName(courseId, courseWorkId);
  return (
    <Anchor
      component={Link}
      href={`/courses/${courseId}/work/${courseWorkId}`}
      key={1}
    >
      {courseWorkName}
    </Anchor>
  );
}
