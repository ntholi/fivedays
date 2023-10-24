import { Anchor, Breadcrumbs } from '@mantine/core';
import React, { Suspense } from 'react';
import CourseLink from './CourseLink';
import CourseWorkLink from './CourseWorkLink';
import { classroom_v1 } from 'googleapis';
import Link from 'next/link';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function CourseWorkBreadcrumbs({ courseWork }: Props) {
  const items = [
    <Anchor component={Link} href={'/courses'} key={1}>
      Courses
    </Anchor>,
    <Suspense fallback={<div>Course</div>} key={2}>
      <CourseLink courseId={courseWork.courseId} />
    </Suspense>,
    <Suspense fallback={<div>Assessment</div>} key={2}>
      <CourseWorkLink
        courseId={courseWork.courseId}
        courseWorkId={courseWork.id}
      />
    </Suspense>,
  ];

  return <Breadcrumbs>{items}</Breadcrumbs>;
}
